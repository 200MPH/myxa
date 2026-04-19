<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\Commands\QueueFailedCommand;
use App\Console\Commands\QueueFlushFailedCommand;
use App\Console\Commands\QueueForgetFailedCommand;
use App\Console\Commands\QueuePruneFailedCommand;
use App\Console\Commands\QueueRetryCommand;
use App\Console\Commands\QueueRetryAllCommand;
use App\Console\Commands\QueueStatusCommand;
use App\Console\Commands\QueueWorkCommand;
use App\Queue\File\FileQueue;
use App\Queue\QueueWorker;
use App\Queue\SerializedJobCodec;
use App\Queue\SimpleRetryPolicy;
use Myxa\Console\CommandInterface;
use Myxa\Console\CommandRunner;
use Myxa\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\Fixtures\Queue\QueueCommandFailingJob;
use Test\Fixtures\Queue\QueueCommandRetryableJob;
use Test\Fixtures\Queue\QueueCommandWriteMarkerJob;
use Test\TestCase;

#[CoversClass(QueueWorkCommand::class)]
#[CoversClass(QueueStatusCommand::class)]
#[CoversClass(QueueFailedCommand::class)]
#[CoversClass(QueueFlushFailedCommand::class)]
#[CoversClass(QueueRetryCommand::class)]
#[CoversClass(QueueRetryAllCommand::class)]
#[CoversClass(QueuePruneFailedCommand::class)]
#[CoversClass(QueueForgetFailedCommand::class)]
final class QueueCommandsTest extends TestCase
{
    private string $queuePath;
    private string $markerPath;
    private string $retryAttemptPath;
    private string $retryProcessedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid('', true);
        $this->queuePath = storage_path('framework/testing/queue-commands-' . $suffix);
        $this->markerPath = storage_path('framework/testing/queue-marker-' . $suffix . '.txt');
        $this->retryAttemptPath = storage_path('framework/testing/queue-retry-attempt-' . $suffix . '.txt');
        $this->retryProcessedPath = storage_path('framework/testing/queue-retry-processed-' . $suffix . '.txt');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->queuePath);

        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        if (is_file($this->retryAttemptPath)) {
            unlink($this->retryAttemptPath);
        }

        if (is_file($this->retryProcessedPath)) {
            unlink($this->retryProcessedPath);
        }

        parent::tearDown();
    }

    public function testQueueStatusCommandRendersCounts(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());
        $queue->push(new QueueCommandWriteMarkerJob($this->markerPath), queue: 'emails');

        $command = new QueueStatusCommand($queue);

        [$exitCode, $output] = $this->runCommand($command, 'queue:status', ['queue' => 'emails']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('emails', $output);
        self::assertStringContainsString('1', $output);
    }

    public function testQueueWorkProcessesOneJobAndFailedCommandsManageFailures(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());
        $worker = new QueueWorker($queue, new SimpleRetryPolicy(1, 0), sleepSeconds: 0, maxIdleCycles: 1);

        $queue->push(new QueueCommandWriteMarkerJob($this->markerPath), queue: 'emails');

        $workCommand = new QueueWorkCommand($worker);
        [$exitCode, $output] = $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Queue worker for [emails] finished.', $output);
        self::assertSame('processed', file_get_contents($this->markerPath));

        $queue->push(new QueueCommandFailingJob(), queue: 'emails');
        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);

        $failedCommand = new QueueFailedCommand($queue);
        [$failedExitCode, $failedOutput] = $this->runCommand($failedCommand, 'queue:failed', ['queue' => 'emails']);

        self::assertSame(0, $failedExitCode);
        self::assertStringContainsString('QueueCommandFailingJob', $failedOutput);
        self::assertStringContainsString('job failed intentionally', $failedOutput);

        $flushCommand = new QueueFlushFailedCommand($queue);
        [$flushExitCode, $flushOutput] = $this->runCommand($flushCommand, 'queue:flush-failed', ['queue' => 'emails']);

        self::assertSame(0, $flushExitCode);
        self::assertStringContainsString('Removed 1 failed job(s)', $flushOutput);
    }

    public function testQueueRetryAndForgetFailedCommandsManageDlqEntries(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());
        $worker = new QueueWorker($queue, new SimpleRetryPolicy(1, 0), sleepSeconds: 0, maxIdleCycles: 1);
        $workCommand = new QueueWorkCommand($worker);

        $jobId = $queue->push(
            new QueueCommandRetryableJob($this->retryAttemptPath, $this->retryProcessedPath),
            queue: 'emails',
        );

        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);

        $retryCommand = new QueueRetryCommand($queue);
        [$retryExitCode, $retryOutput] = $this->runCommand($retryCommand, 'queue:retry', ['id' => $jobId]);

        self::assertSame(0, $retryExitCode);
        self::assertStringContainsString('moved back to the queue', $retryOutput);

        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);
        self::assertSame('processed-after-retry', file_get_contents($this->retryProcessedPath));

        $queue->push(new QueueCommandFailingJob(), queue: 'emails');
        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);
        $failed = $queue->failed('emails');

        self::assertCount(1, $failed);

        $forgetCommand = new QueueForgetFailedCommand($queue);
        [$forgetExitCode, $forgetOutput] = $this->runCommand(
            $forgetCommand,
            'queue:forget-failed',
            ['id' => $failed[0]->id],
        );

        self::assertSame(0, $forgetExitCode);
        self::assertStringContainsString('deleted from the dead-letter store', $forgetOutput);
        self::assertSame([], $queue->failed('emails'));
    }

    public function testQueueRetryAllCommandRetriesBulkDlqEntries(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());
        $worker = new QueueWorker($queue, new SimpleRetryPolicy(1, 0), sleepSeconds: 0, maxIdleCycles: 1);
        $workCommand = new QueueWorkCommand($worker);

        $queue->push(new QueueCommandFailingJob(), queue: 'emails');
        $queue->push(new QueueCommandFailingJob(), queue: 'emails');

        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);
        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);

        $retryAllCommand = new QueueRetryAllCommand($queue);
        [$exitCode, $output] = $this->runCommand($retryAllCommand, 'queue:retry-all', ['queue' => 'emails']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Retried 2 failed job(s) from [emails].', $output);
        self::assertCount(0, $queue->failed('emails'));
    }

    public function testQueuePruneFailedCommandPrunesByAge(): void
    {
        $queue = new FileQueue($this->queuePath, new SerializedJobCodec());
        $worker = new QueueWorker($queue, new SimpleRetryPolicy(1, 0), sleepSeconds: 0, maxIdleCycles: 1);
        $workCommand = new QueueWorkCommand($worker);

        $oldId = $queue->push(new QueueCommandFailingJob(), queue: 'emails');
        $recentId = $queue->push(new QueueCommandFailingJob(), queue: 'emails');

        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);
        $this->runCommand($workCommand, 'queue:work', ['queue' => 'emails'], ['once' => true]);

        $this->rewriteFailedTimestamp($oldId, gmdate('c', time() - 8 * 86400));

        $pruneCommand = new QueuePruneFailedCommand($queue);
        [$exitCode, $output] = $this->runCommand(
            $pruneCommand,
            'queue:prune-failed',
            ['queue' => 'emails'],
            ['older-than' => '7d'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Pruned 1 failed job(s) from [emails].', $output);

        $remaining = $queue->failed('emails');
        self::assertCount(1, $remaining);
        self::assertSame($recentId, $remaining[0]->id);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     * @return array{0: int, 1: string}
     */
    private function runCommand(
        CommandInterface $command,
        string $name,
        array $parameters = [],
        array $options = [],
    ): array {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $runner = new CommandRunner(new Container(), output: $stream);
        $runner->register($command);
        $exitCode = $runner->run($this->argv($name, $parameters, $options));

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function argv(string $name, array $parameters, array $options): array
    {
        $argv = ['myxa', $name];

        foreach ($parameters as $value) {
            $argv[] = (string) $value;
        }

        foreach ($options as $option => $value) {
            if ($value === true) {
                $argv[] = '--' . $option;

                continue;
            }

            $argv[] = sprintf('--%s=%s', $option, (string) $value);
        }

        return $argv;
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
