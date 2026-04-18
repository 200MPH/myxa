<?php

declare(strict_types=1);

namespace Test\Unit\Queue;

use App\Queue\File\FileQueue;
use App\Queue\SerializedJobCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Test\Fixtures\Queue\FileQueueTestJob;
use Test\TestCase;

#[CoversClass(FileQueue::class)]
#[CoversClass(SerializedJobCodec::class)]
final class FileQueueTest extends TestCase
{
    private string $queuePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queuePath = storage_path('framework/testing/file-queue-' . uniqid('', true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->queuePath);

        parent::tearDown();
    }

    public function testFileQueueTracksReadyReservedAndAckedJobs(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());

        $queue->push(new FileQueueTestJob(), queue: 'emails');

        $stats = $queue->stats('emails')[0];
        self::assertSame(1, $stats->ready);
        self::assertSame(0, $stats->reserved);

        $message = $queue->pop('emails');

        self::assertNotNull($message);
        self::assertSame('emails', $message->queue);

        $reservedStats = $queue->stats('emails')[0];
        self::assertSame(0, $reservedStats->ready);
        self::assertSame(1, $reservedStats->reserved);

        $queue->ack($message);

        $ackedStats = $queue->stats('emails')[0];
        self::assertSame(0, $ackedStats->ready);
        self::assertSame(0, $ackedStats->reserved);
        self::assertSame(0, $ackedStats->failed);
    }

    public function testFileQueueTracksDelayedReleasedAndFailedJobs(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());

        $queue->push(new FileQueueTestJob(), queue: 'emails');
        $message = $queue->pop('emails');

        self::assertNotNull($message);

        $queue->release($message, 30);

        $delayedStats = $queue->stats('emails')[0];
        self::assertSame(0, $delayedStats->ready);
        self::assertSame(1, $delayedStats->delayed);

        $queue->push(new FileQueueTestJob(), queue: 'emails');
        $failedMessage = $queue->pop('emails');

        self::assertNotNull($failedMessage);

        $queue->fail($failedMessage, new RuntimeException('boom'));

        $records = $queue->failed('emails');

        self::assertCount(1, $records);
        self::assertSame('boom', $records[0]->errorMessage);
        self::assertSame(1, $records[0]->attempts);
        self::assertSame(1, $queue->flushFailed('emails'));
    }

    public function testFileQueueRecoversExpiredReservedJobsAndSupportsDlqRetry(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec(), visibilityTimeoutSeconds: 0);

        $jobId = $queue->push(new FileQueueTestJob(), queue: 'emails');
        $message = $queue->pop('emails');

        self::assertNotNull($message);

        $recovered = $queue->pop('emails');

        self::assertNotNull($recovered);
        self::assertSame($jobId, $recovered->id);

        $queue->fail($recovered, new RuntimeException('boom'));
        self::assertTrue($queue->retryFailed($jobId));

        $retried = $queue->pop('emails');
        self::assertNotNull($retried);
        self::assertSame($jobId, $retried->id);

        $queue->fail($retried, new RuntimeException('boom-again'));
        self::assertTrue($queue->forgetFailed($jobId));
        self::assertSame([], $queue->failed('emails'));
    }

    public function testFileQueueCanRetryAllFailedJobs(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());

        $firstId = $queue->push(new FileQueueTestJob(), queue: 'emails');
        $secondId = $queue->push(new FileQueueTestJob(), queue: 'emails');

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
        $retriedIds = [$retriedFirst->id, $retriedSecond->id];
        sort($retriedIds);
        $expectedIds = [$firstId, $secondId];
        sort($expectedIds);
        self::assertSame($expectedIds, $retriedIds);
    }

    public function testFileQueueCanPruneOldFailedJobs(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());

        $oldId = $queue->push(new FileQueueTestJob(), queue: 'emails');
        $recentId = $queue->push(new FileQueueTestJob(), queue: 'emails');

        $old = $queue->pop('emails');
        $recent = $queue->pop('emails');

        self::assertNotNull($old);
        self::assertNotNull($recent);

        $queue->fail($old, new RuntimeException('old'));
        $queue->fail($recent, new RuntimeException('recent'));

        $this->rewriteFailedTimestamp($oldId, gmdate('c', time() - 8 * 86400));

        self::assertSame(1, $queue->pruneFailed(7 * 86400, 'emails'));

        $remaining = $queue->failed('emails');
        self::assertCount(1, $remaining);
        self::assertSame($recentId, $remaining[0]->id);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }

    private function rewriteFailedTimestamp(string $id, string $failedAt): void
    {
        $paths = glob($this->queuePath . '/failed/*-' . $id . '.json') ?: [];
        self::assertIsString($paths[0] ?? null);
        $path = $paths[0];

        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['failed_at'] = $failedAt;

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
