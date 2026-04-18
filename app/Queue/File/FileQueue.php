<?php

declare(strict_types=1);

namespace App\Queue\File;

use App\Queue\InspectableQueueInterface;
use App\Queue\QueueStats;
use App\Queue\SerializedJobCodec;
use Myxa\Queue\JobEnvelope;
use Myxa\Queue\JobInterface;
use RuntimeException;
use Throwable;

final class FileQueue implements InspectableQueueInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly SerializedJobCodec $codec,
        private readonly string $defaultQueue = 'default',
        private readonly int $visibilityTimeoutSeconds = 60,
    ) {
    }

    public function push(JobInterface $job, array $context = [], ?string $queue = null): string
    {
        $payload = $this->codec->encode($job, $context, $queue, $this->defaultQueue);

        $this->writeJson($this->pendingPath($payload), $payload);

        return (string) $payload['id'];
    }

    public function pop(?string $queue = null): ?JobEnvelope
    {
        $this->ensureDirectories();
        $this->recoverExpiredReservations($queue);
        $paths = glob($this->pendingDirectory() . '/*.json') ?: [];
        sort($paths, SORT_STRING);
        $now = time();

        foreach ($paths as $path) {
            $payload = $this->readJson($path);
            $payloadQueue = (string) ($payload['queue'] ?? $this->defaultQueue);
            $availableAt = is_numeric($payload['available_at'] ?? null) ? (int) $payload['available_at'] : 0;

            if ($queue !== null && $payloadQueue !== trim($queue)) {
                continue;
            }

            if ($availableAt > $now) {
                continue;
            }

            $reservedPath = $this->reservedDirectory() . '/' . $payload['id'] . '.json';

            if (!@rename($path, $reservedPath)) {
                continue;
            }

            $payload['reserved_at'] = $now;
            $this->writeJson($reservedPath, $payload);

            return $this->codec->envelope($payload);
        }

        return null;
    }

    public function ack(JobEnvelope $message): void
    {
        $reservedPath = $this->reservedDirectory() . '/' . $message->id . '.json';
        if (is_file($reservedPath)) {
            unlink($reservedPath);
        }
    }

    public function release(JobEnvelope $message, int $delaySeconds = 0): void
    {
        $this->deleteReserved($message->id);
        $payload = $this->codec->release($message, $delaySeconds);
        $this->writeJson($this->pendingPath($payload), $payload);
    }

    public function fail(JobEnvelope $message, ?Throwable $error = null): void
    {
        $this->deleteReserved($message->id);
        $payload = $this->codec->fail($message, $error);
        $this->writeJson($this->failedPath($payload), $payload);
    }

    public function stats(?string $queue = null): array
    {
        $this->recoverExpiredReservations($queue);
        $queues = $queue !== null ? [trim($queue)] : $this->knownQueues();
        if ($queues === []) {
            $queues = [$this->defaultQueue];
        }

        $stats = [];

        foreach ($queues as $queueName) {
            $counts = [
                'ready' => 0,
                'delayed' => 0,
                'reserved' => 0,
                'failed' => 0,
            ];

            foreach ($this->pendingPayloads() as $payload) {
                if (($payload['queue'] ?? $this->defaultQueue) !== $queueName) {
                    continue;
                }

                $availableAt = is_numeric($payload['available_at'] ?? null) ? (int) $payload['available_at'] : 0;
                $counts[$availableAt > time() ? 'delayed' : 'ready']++;
            }

            foreach ($this->reservedPayloads() as $payload) {
                if (($payload['queue'] ?? $this->defaultQueue) === $queueName) {
                    $counts['reserved']++;
                }
            }

            foreach ($this->failedPayloads() as $payload) {
                if (($payload['queue'] ?? $this->defaultQueue) === $queueName) {
                    $counts['failed']++;
                }
            }

            $stats[] = new QueueStats(
                queue: $queueName,
                ready: $counts['ready'],
                delayed: $counts['delayed'],
                reserved: $counts['reserved'],
                failed: $counts['failed'],
            );
        }

        return $stats;
    }

    public function failed(?string $queue = null, int $limit = 50): array
    {
        $records = [];
        $payloads = $this->failedPayloads();
        usort(
            $payloads,
            static fn (array $left, array $right): int => strcmp(
                (string) ($right['failed_at'] ?? ''),
                (string) ($left['failed_at'] ?? ''),
            ),
        );

        foreach ($payloads as $payload) {
            if ($queue !== null && ($payload['queue'] ?? $this->defaultQueue) !== trim($queue)) {
                continue;
            }

            $records[] = $this->codec->failedRecord($payload);

            if ($limit > 0 && count($records) >= $limit) {
                break;
            }
        }

        return $records;
    }

    public function retryFailed(string $id, ?string $targetQueue = null): bool
    {
        $path = $this->failedPathForId($id);
        if ($path === null) {
            return false;
        }

        $payload = $this->readJson($path);
        $retryPayload = $this->codec->retryFailed($payload, $targetQueue);

        unlink($path);
        $this->writeJson($this->pendingPath($retryPayload), $retryPayload);

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
        $path = $this->failedPathForId($id);
        if ($path === null) {
            return false;
        }

        unlink($path);

        return true;
    }

    public function pruneFailed(int $olderThanSeconds, ?string $queue = null): int
    {
        $pruned = 0;
        $cutoff = time() - max(0, $olderThanSeconds);

        foreach (glob($this->failedDirectory() . '/*.json') ?: [] as $path) {
            $payload = $this->readJson($path);
            $payloadQueue = (string) ($payload['queue'] ?? $this->defaultQueue);

            if ($queue !== null && trim($queue) !== $payloadQueue) {
                continue;
            }

            $failedAt = strtotime((string) ($payload['failed_at'] ?? ''));
            if ($failedAt === false || $failedAt > $cutoff) {
                continue;
            }

            unlink($path);
            $pruned++;
        }

        return $pruned;
    }

    public function flushFailed(?string $queue = null): int
    {
        $count = 0;

        foreach (glob($this->failedDirectory() . '/*.json') ?: [] as $path) {
            $payload = $this->readJson($path);

            if ($queue !== null && ($payload['queue'] ?? $this->defaultQueue) !== trim($queue)) {
                continue;
            }

            if (is_file($path)) {
                unlink($path);
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
        $queues = [];

        foreach ([$this->pendingPayloads(), $this->reservedPayloads(), $this->failedPayloads()] as $payloads) {
            foreach ($payloads as $payload) {
                $queue = trim((string) ($payload['queue'] ?? $this->defaultQueue));
                if ($queue !== '') {
                    $queues[$queue] = true;
                }
            }
        }

        $queueNames = array_keys($queues);
        sort($queueNames);

        return $queueNames;
    }

    private function recoverExpiredReservations(?string $queue = null): int
    {
        if ($this->visibilityTimeoutSeconds < 0) {
            return 0;
        }

        $recovered = 0;
        $now = time();

        foreach (glob($this->reservedDirectory() . '/*.json') ?: [] as $path) {
            $payload = $this->readJson($path);
            $payloadQueue = (string) ($payload['queue'] ?? $this->defaultQueue);

            if ($queue !== null && trim($queue) !== $payloadQueue) {
                continue;
            }

            $reservedAt = is_numeric($payload['reserved_at'] ?? null)
                ? (int) $payload['reserved_at']
                : ((int) filemtime($path) ?: $now);

            if (($reservedAt + $this->visibilityTimeoutSeconds) > $now) {
                continue;
            }

            unset($payload['reserved_at']);
            $payload['available_at'] = $now;
            unlink($path);
            $this->writeJson($this->pendingPath($payload), $payload);
            $recovered++;
        }

        return $recovered;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingPayloads(): array
    {
        return $this->readDirectoryPayloads($this->pendingDirectory());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reservedPayloads(): array
    {
        return $this->readDirectoryPayloads($this->reservedDirectory());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function failedPayloads(): array
    {
        return $this->readDirectoryPayloads($this->failedDirectory());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readDirectoryPayloads(string $directory): array
    {
        $this->ensureDirectories();
        $payloads = [];

        foreach (glob($directory . '/*.json') ?: [] as $path) {
            $payloads[] = $this->readJson($path);
        }

        return $payloads;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function pendingPath(array $payload): string
    {
        $availableAt = is_numeric($payload['available_at'] ?? null) ? (int) $payload['available_at'] : time();

        return sprintf(
            '%s/%011d-%s.json',
            $this->pendingDirectory(),
            $availableAt,
            (string) $payload['id'],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function failedPath(array $payload): string
    {
        return sprintf(
            '%s/%s-%s.json',
            $this->failedDirectory(),
            preg_replace('/[^0-9]/', '', (string) ($payload['failed_at'] ?? gmdate('c'))) ?: time(),
            (string) $payload['id'],
        );
    }

    private function pendingDirectory(): string
    {
        return rtrim($this->basePath, '/') . '/pending';
    }

    private function reservedDirectory(): string
    {
        return rtrim($this->basePath, '/') . '/reserved';
    }

    private function failedDirectory(): string
    {
        return rtrim($this->basePath, '/') . '/failed';
    }

    private function failedPathForId(string $id): ?string
    {
        $paths = glob($this->failedDirectory() . '/*-' . $id . '.json') ?: [];

        return is_string($paths[0] ?? null) ? $paths[0] : null;
    }

    private function ensureDirectories(): void
    {
        foreach ([$this->pendingDirectory(), $this->reservedDirectory(), $this->failedDirectory()] as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create queue directory [%s].', $directory));
            }
        }
    }

    private function deleteReserved(string $id): void
    {
        $reservedPath = $this->reservedDirectory() . '/' . $id . '.json';
        if (is_file($reservedPath)) {
            unlink($reservedPath);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $this->ensureDirectories();
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        file_put_contents($path, $encoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException(sprintf('Queue payload file [%s] is empty or unreadable.', $path));
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new RuntimeException(sprintf('Queue payload file [%s] is invalid.', $path));
        }

        return $payload;
    }
}
