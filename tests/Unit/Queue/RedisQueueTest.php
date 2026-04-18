<?php

declare(strict_types=1);

namespace Test\Unit\Queue;

use App\Queue\Redis\RedisQueue;
use App\Queue\SerializedJobCodec;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Test\Fixtures\Queue\RedisQueueTestJob;
use Test\TestCase;

#[CoversClass(RedisQueue::class)]
#[CoversClass(SerializedJobCodec::class)]
final class RedisQueueTest extends TestCase
{
    public function testRedisQueueTracksReadyReservedAndAckedJobs(): void
    {
        $queue = new RedisQueue(
            new RedisConnection(new InMemoryRedisStore()),
            new SerializedJobCodec(),
            'queue:test:',
        );

        $queue->push(new RedisQueueTestJob(), queue: 'emails');

        $stats = $queue->stats('emails')[0];
        self::assertSame(1, $stats->ready);

        $message = $queue->pop('emails');

        self::assertNotNull($message);
        self::assertSame('emails', $message->queue);

        $reservedStats = $queue->stats('emails')[0];
        self::assertSame(1, $reservedStats->reserved);

        $queue->ack($message);

        $ackedStats = $queue->stats('emails')[0];
        self::assertSame(0, $ackedStats->ready);
        self::assertSame(0, $ackedStats->reserved);
    }

    public function testRedisQueueRecordsAndFlushesFailedJobs(): void
    {
        $queue = new RedisQueue(
            new RedisConnection(new InMemoryRedisStore()),
            new SerializedJobCodec(),
            'queue:test:',
        );

        $queue->push(new RedisQueueTestJob(), queue: 'emails');
        $message = $queue->pop('emails');

        self::assertNotNull($message);

        $queue->fail($message, new RuntimeException('redis-boom'));

        $records = $queue->failed('emails');

        self::assertCount(1, $records);
        self::assertSame('redis-boom', $records[0]->errorMessage);
        self::assertSame(1, $queue->flushFailed('emails'));
        self::assertSame(0, $queue->stats('emails')[0]->failed);
    }

    public function testRedisQueueRecoversExpiredReservedJobsAndSupportsDlqRetry(): void
    {
        $queue = new RedisQueue(
            new RedisConnection(new InMemoryRedisStore()),
            new SerializedJobCodec(),
            'queue:test:',
            visibilityTimeoutSeconds: 0,
        );

        $jobId = $queue->push(new RedisQueueTestJob(), queue: 'emails');
        $message = $queue->pop('emails');

        self::assertNotNull($message);

        $recovered = $queue->pop('emails');

        self::assertNotNull($recovered);
        self::assertSame($jobId, $recovered->id);

        $queue->fail($recovered, new RuntimeException('redis-boom'));
        self::assertTrue($queue->retryFailed($jobId));

        $retried = $queue->pop('emails');
        self::assertNotNull($retried);
        self::assertSame($jobId, $retried->id);

        $queue->fail($retried, new RuntimeException('redis-boom-again'));
        self::assertTrue($queue->forgetFailed($jobId));
        self::assertSame([], $queue->failed('emails'));
    }

    public function testRedisQueueCanRetryAllFailedJobs(): void
    {
        $queue = new RedisQueue(new RedisConnection(new InMemoryRedisStore()), new SerializedJobCodec(), 'queue:test:');

        $firstId = $queue->push(new RedisQueueTestJob(), queue: 'emails');
        $secondId = $queue->push(new RedisQueueTestJob(), queue: 'emails');

        $first = $queue->pop('emails');
        $second = $queue->pop('emails');

        self::assertNotNull($first);
        self::assertNotNull($second);

        $queue->fail($first, new RuntimeException('one'));
        $queue->fail($second, new RuntimeException('two'));

        self::assertSame(2, $queue->retryAllFailed('emails'));

        $retriedFirst = $queue->pop('emails');
        $retriedSecond = $queue->pop('emails');

        self::assertNotNull($retriedFirst);
        self::assertNotNull($retriedSecond);
        self::assertSame([$firstId, $secondId], [$retriedFirst->id, $retriedSecond->id]);
    }

    public function testRedisQueueCanPruneOldFailedJobs(): void
    {
        $store = new InMemoryRedisStore();
        $queue = new RedisQueue(new RedisConnection($store), new SerializedJobCodec(), 'queue:test:');

        $oldId = $queue->push(new RedisQueueTestJob(), queue: 'emails');
        $recentId = $queue->push(new RedisQueueTestJob(), queue: 'emails');

        $old = $queue->pop('emails');
        $recent = $queue->pop('emails');

        self::assertNotNull($old);
        self::assertNotNull($recent);

        $queue->fail($old, new RuntimeException('old'));
        $queue->fail($recent, new RuntimeException('recent'));

        $this->rewriteRedisFailedTimestamp($store, 'queue:test:', $oldId, gmdate('c', time() - 8 * 86400));

        self::assertSame(1, $queue->pruneFailed(7 * 86400, 'emails'));

        $remaining = $queue->failed('emails');
        self::assertCount(1, $remaining);
        self::assertSame($recentId, $remaining[0]->id);
    }

    private function rewriteRedisFailedTimestamp(
        InMemoryRedisStore $store,
        string $prefix,
        string $id,
        string $failedAt,
    ): void {
        $key = $prefix . 'payload:' . $id;
        $payload = json_decode((string) $store->get($key), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['failed_at'] = $failedAt;
        $store->set($key, json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
