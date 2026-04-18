<?php

declare(strict_types=1);

namespace App\Queue\Redis;

use App\Queue\InspectableQueueInterface;
use App\Queue\QueueStats;
use App\Queue\SerializedJobCodec;
use Myxa\Queue\JobEnvelope;
use Myxa\Queue\JobInterface;
use Myxa\Redis\Connection\PhpRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use RuntimeException;
use Throwable;

final class RedisQueue implements InspectableQueueInterface
{
    public function __construct(
        private readonly RedisConnection $connection,
        private readonly SerializedJobCodec $codec,
        private readonly string $prefix = 'queue:',
        private readonly string $defaultQueue = 'default',
        private readonly int $visibilityTimeoutSeconds = 60,
    ) {
    }

    public function push(JobInterface $job, array $context = [], ?string $queue = null): string
    {
        $payload = $this->codec->encode($job, $context, $queue, $this->defaultQueue);
        $queueName = (string) $payload['queue'];

        $this->rememberQueue($queueName);
        $this->storePayload($payload);

        if (((int) ($payload['available_at'] ?? time())) > time()) {
            $this->pushDelayed($queueName, (string) $payload['id'], (int) $payload['available_at']);
        } else {
            $this->pushReady($queueName, (string) $payload['id']);
        }

        return (string) $payload['id'];
    }

    public function pop(?string $queue = null): ?JobEnvelope
    {
        $queueName = $this->resolveQueue($queue);
        $this->recoverExpiredReservations($queueName);
        $this->promoteDelayed($queueName);
        $id = $this->popReady($queueName);

        if ($id === null) {
            return null;
        }

        $payload = $this->payload($id);
        if ($payload === null) {
            return null;
        }

        $this->markReserved($queueName, $id);

        return $this->codec->envelope($payload);
    }

    public function ack(JobEnvelope $message): void
    {
        $queueName = $this->resolveQueue($message->queue);
        $this->removeReserved($queueName, $message->id);
        $this->deletePayload($message->id);
    }

    public function release(JobEnvelope $message, int $delaySeconds = 0): void
    {
        $queueName = $this->resolveQueue($message->queue);
        $payload = $this->codec->release($message, $delaySeconds);

        $this->removeReserved($queueName, $message->id);
        $this->storePayload($payload);

        if ($delaySeconds > 0) {
            $this->pushDelayed($queueName, $message->id, (int) $payload['available_at']);

            return;
        }

        $this->pushReady($queueName, $message->id);
    }

    public function fail(JobEnvelope $message, ?Throwable $error = null): void
    {
        $queueName = $this->resolveQueue($message->queue);
        $payload = $this->codec->fail($message, $error);

        $this->removeReserved($queueName, $message->id);
        $this->storePayload($payload);
        $this->markFailed($queueName, $message->id);
    }

    public function stats(?string $queue = null): array
    {
        $queues = $queue !== null ? [$this->resolveQueue($queue)] : $this->knownQueues();
        if ($queues === []) {
            $queues = [$this->defaultQueue];
        }

        $stats = [];

        foreach ($queues as $queueName) {
            $this->recoverExpiredReservations($queueName);

            $stats[] = new QueueStats(
                queue: $queueName,
                ready: $this->readyCount($queueName),
                delayed: $this->delayedCount($queueName),
                reserved: $this->reservedCount($queueName),
                failed: $this->failedCount($queueName),
            );
        }

        return $stats;
    }

    public function failed(?string $queue = null, int $limit = 50): array
    {
        $records = [];
        $queues = $queue !== null ? [$this->resolveQueue($queue)] : $this->knownQueues();

        foreach ($queues as $queueName) {
            foreach ($this->failedIds($queueName, $limit) as $id) {
                $payload = $this->payload($id);
                if ($payload === null) {
                    continue;
                }

                $records[] = $this->codec->failedRecord($payload);
            }
        }

        usort(
            $records,
            static fn ($left, $right): int => strcmp($right->failedAt, $left->failedAt),
        );

        if ($limit > 0) {
            return array_slice($records, 0, $limit);
        }

        return $records;
    }

    public function retryFailed(string $id, ?string $targetQueue = null): bool
    {
        $payload = $this->payload($id);
        if ($payload === null) {
            return false;
        }

        $queueName = $this->resolveQueue((string) ($payload['queue'] ?? $this->defaultQueue));
        if (!$this->isFailed($queueName, $id)) {
            return false;
        }

        $retryPayload = $this->codec->retryFailed($payload, $targetQueue);
        $retryQueue = $this->resolveQueue((string) ($retryPayload['queue'] ?? $queueName));

        $this->removeFailed($queueName, $id);
        $this->storePayload($retryPayload);
        $this->rememberQueue($retryQueue);
        $this->pushReady($retryQueue, $id);

        return true;
    }

    public function retryAllFailed(?string $queue = null, int $limit = 0, ?string $targetQueue = null): int
    {
        $retried = 0;

        foreach ($this->failed($queue, $limit) as $record) {
            if (!$this->retryFailed($record->id, $targetQueue)) {
                continue;
            }

            $retried++;
        }

        return $retried;
    }

    public function forgetFailed(string $id): bool
    {
        $payload = $this->payload($id);
        if ($payload === null) {
            return false;
        }

        $queueName = $this->resolveQueue((string) ($payload['queue'] ?? $this->defaultQueue));
        if (!$this->isFailed($queueName, $id)) {
            return false;
        }

        $this->removeFailed($queueName, $id);
        $this->deletePayload($id);

        return true;
    }

    public function pruneFailed(int $olderThanSeconds, ?string $queue = null): int
    {
        $pruned = 0;
        $cutoff = time() - max(0, $olderThanSeconds);

        foreach ($this->failed($queue, 0) as $record) {
            $failedAt = strtotime($record->failedAt);
            if ($failedAt === false || $failedAt > $cutoff) {
                continue;
            }

            if ($this->forgetFailed($record->id)) {
                $pruned++;
            }
        }

        return $pruned;
    }

    public function flushFailed(?string $queue = null): int
    {
        $queues = $queue !== null ? [$this->resolveQueue($queue)] : $this->knownQueues();
        $count = 0;

        foreach ($queues as $queueName) {
            foreach ($this->failedIds($queueName, 0) as $id) {
                $this->removeFailed($queueName, $id);
                $this->deletePayload($id);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function knownQueues(): array
    {
        if ($this->nativeClient() instanceof \Redis) {
            $queues = $this->nativeClient()->sMembers($this->knownQueuesKey()) ?: [];

            return array_values(array_filter($queues, 'is_string'));
        }

        $queues = $this->readJsonList($this->knownQueuesKey());
        sort($queues);

        return $queues;
    }

    private function rememberQueue(string $queue): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->sAdd($this->knownQueuesKey(), $queue);

            return;
        }

        $queues = $this->readJsonList($this->knownQueuesKey());
        $queues[] = $queue;
        $queues = array_values(array_unique(array_filter($queues, 'is_string')));
        sort($queues);
        $this->writeJsonList($this->knownQueuesKey(), $queues);
    }

    private function resolveQueue(?string $queue): string
    {
        $queue = is_string($queue) ? trim($queue) : '';

        return $queue !== '' ? $queue : $this->defaultQueue;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storePayload(array $payload): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->connection->setValue($this->payloadKey((string) $payload['id']), $encoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(string $id): ?array
    {
        $encoded = $this->connection->getValue($this->payloadKey($id));

        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $payload = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload : null;
    }

    private function deletePayload(string $id): void
    {
        $this->connection->delete($this->payloadKey($id));
    }

    private function pushReady(string $queue, string $id): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->rPush($this->readyKey($queue), $id);

            return;
        }

        $values = $this->readJsonList($this->readyKey($queue));
        $values[] = $id;
        $this->writeJsonList($this->readyKey($queue), $values);
    }

    private function popReady(string $queue): ?string
    {
        if ($this->nativeClient() instanceof \Redis) {
            $id = $this->nativeClient()->lPop($this->readyKey($queue));

            return is_string($id) && $id !== '' ? $id : null;
        }

        $values = $this->readJsonList($this->readyKey($queue));
        $id = array_shift($values);
        $this->writeJsonList($this->readyKey($queue), $values);

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function readyCount(string $queue): int
    {
        if ($this->nativeClient() instanceof \Redis) {
            return (int) $this->nativeClient()->lLen($this->readyKey($queue));
        }

        return count($this->readJsonList($this->readyKey($queue)));
    }

    private function pushDelayed(string $queue, string $id, int $availableAt): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->zAdd($this->delayedKey($queue), $availableAt, $id);

            return;
        }

        $values = $this->readJsonMap($this->delayedKey($queue));
        $values[$id] = $availableAt;
        $this->writeJsonMap($this->delayedKey($queue), $values);
    }

    private function promoteDelayed(string $queue): void
    {
        $now = time();

        $client = $this->nativeClient();

        if ($client instanceof \Redis) {
            $ids = $client->zRangeByScore($this->delayedKey($queue), '-inf', (string) $now);
            if (!is_array($ids)) {
                return;
            }

            foreach ($ids as $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }

                if ((int) $client->zRem($this->delayedKey($queue), $id) > 0) {
                    $client->rPush($this->readyKey($queue), $id);
                }
            }

            return;
        }

        $values = $this->readJsonMap($this->delayedKey($queue));

        foreach ($values as $id => $availableAt) {
            if (!is_string($id) || $availableAt > $now) {
                continue;
            }

            unset($values[$id]);
            $this->pushReady($queue, $id);
        }

        $this->writeJsonMap($this->delayedKey($queue), $values);
    }

    private function delayedCount(string $queue): int
    {
        if ($this->nativeClient() instanceof \Redis) {
            return (int) $this->nativeClient()->zCard($this->delayedKey($queue));
        }

        return count($this->readJsonMap($this->delayedKey($queue)));
    }

    private function recoverExpiredReservations(string $queue): int
    {
        if ($this->visibilityTimeoutSeconds < 0) {
            return 0;
        }

        $cutoff = time() - $this->visibilityTimeoutSeconds;
        $recovered = 0;

        $client = $this->nativeClient();

        if ($client instanceof \Redis) {
            $ids = $client->zRangeByScore($this->reservedKey($queue), '-inf', (string) $cutoff);
            if (!is_array($ids)) {
                return 0;
            }

            foreach ($ids as $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }

                if ((int) $client->zRem($this->reservedKey($queue), $id) > 0) {
                    $client->rPush($this->readyKey($queue), $id);
                    $recovered++;
                }
            }

            return $recovered;
        }

        $values = $this->readJsonMap($this->reservedKey($queue));

        foreach ($values as $id => $reservedAt) {
            if ($reservedAt > $cutoff) {
                continue;
            }

            unset($values[$id]);
            $this->pushReady($queue, $id);
            $recovered++;
        }

        $this->writeJsonMap($this->reservedKey($queue), $values);

        return $recovered;
    }

    private function markReserved(string $queue, string $id): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->zAdd($this->reservedKey($queue), time(), $id);

            return;
        }

        $values = $this->readJsonMap($this->reservedKey($queue));
        $values[$id] = time();
        $this->writeJsonMap($this->reservedKey($queue), $values);
    }

    private function removeReserved(string $queue, string $id): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->zRem($this->reservedKey($queue), $id);

            return;
        }

        $values = $this->readJsonMap($this->reservedKey($queue));
        unset($values[$id]);
        $this->writeJsonMap($this->reservedKey($queue), $values);
    }

    private function reservedCount(string $queue): int
    {
        if ($this->nativeClient() instanceof \Redis) {
            return (int) $this->nativeClient()->zCard($this->reservedKey($queue));
        }

        return count($this->readJsonMap($this->reservedKey($queue)));
    }

    private function markFailed(string $queue, string $id): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->zAdd($this->failedKey($queue), time(), $id);

            return;
        }

        $values = $this->readJsonMap($this->failedKey($queue));
        $values[$id] = time();
        $this->writeJsonMap($this->failedKey($queue), $values);
    }

    /**
     * @return list<string>
     */
    private function failedIds(string $queue, int $limit): array
    {
        if ($this->nativeClient() instanceof \Redis) {
            $end = $limit > 0 ? $limit - 1 : -1;
            $ids = $this->nativeClient()->zRevRange($this->failedKey($queue), 0, $end);

            return array_values(array_filter($ids ?: [], 'is_string'));
        }

        $values = $this->readJsonMap($this->failedKey($queue));
        arsort($values, SORT_NUMERIC);
        $ids = array_keys($values);

        return $limit > 0 ? array_slice($ids, 0, $limit) : $ids;
    }

    private function removeFailed(string $queue, string $id): void
    {
        if ($this->nativeClient() instanceof \Redis) {
            $this->nativeClient()->zRem($this->failedKey($queue), $id);

            return;
        }

        $values = $this->readJsonMap($this->failedKey($queue));
        unset($values[$id]);
        $this->writeJsonMap($this->failedKey($queue), $values);
    }

    private function failedCount(string $queue): int
    {
        if ($this->nativeClient() instanceof \Redis) {
            return (int) $this->nativeClient()->zCard($this->failedKey($queue));
        }

        return count($this->readJsonMap($this->failedKey($queue)));
    }

    private function isFailed(string $queue, string $id): bool
    {
        if ($this->nativeClient() instanceof \Redis) {
            $rank = $this->nativeClient()->zRank($this->failedKey($queue), $id);

            return $rank !== false;
        }

        return array_key_exists($id, $this->readJsonMap($this->failedKey($queue)));
    }

    private function knownQueuesKey(): string
    {
        return $this->prefix . 'known-queues';
    }

    private function payloadKey(string $id): string
    {
        return $this->prefix . 'payload:' . $id;
    }

    private function readyKey(string $queue): string
    {
        return $this->prefix . 'ready:' . $queue;
    }

    private function delayedKey(string $queue): string
    {
        return $this->prefix . 'delayed:' . $queue;
    }

    private function reservedKey(string $queue): string
    {
        return $this->prefix . 'reserved:' . $queue;
    }

    private function failedKey(string $queue): string
    {
        return $this->prefix . 'failed:' . $queue;
    }

    /**
     * @return list<string>
     */
    private function readJsonList(string $key): array
    {
        $encoded = $this->connection->getValue($key);
        if (!is_string($encoded) || $encoded === '') {
            return [];
        }

        $decoded = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    /**
     * @param list<string> $values
     */
    private function writeJsonList(string $key, array $values): void
    {
        $this->connection->setValue($key, json_encode(array_values($values), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, int>
     */
    private function readJsonMap(string $key): array
    {
        $encoded = $this->connection->getValue($key);
        if (!is_string($encoded) || $encoded === '') {
            return [];
        }

        $decoded = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return [];
        }

        $values = [];

        foreach ($decoded as $mapKey => $value) {
            if (!is_string($mapKey) || !is_numeric($value)) {
                continue;
            }

            $values[$mapKey] = (int) $value;
        }

        return $values;
    }

    /**
     * @param array<string, int> $values
     */
    private function writeJsonMap(string $key, array $values): void
    {
        $this->connection->setValue($key, json_encode($values, JSON_THROW_ON_ERROR));
    }

    private function nativeClient(): ?\Redis
    {
        $store = $this->connection->store();

        if (!$store instanceof PhpRedisStore) {
            return null;
        }

        $client = $store->client();

        $requiredMethods = [
            'rPush',
            'lPop',
            'lLen',
            'zAdd',
            'zRangeByScore',
            'zRem',
            'zCard',
            'zRevRange',
            'zRank',
            'sAdd',
            'sMembers',
        ];

        foreach ($requiredMethods as $method) {
            if (!method_exists($client, $method)) {
                return null;
            }
        }

        return $client;
    }
}
