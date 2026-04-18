<?php

declare(strict_types=1);

namespace App\Queue;

use Myxa\Queue\JobEnvelope;
use Myxa\Queue\QueueInterface;
use Myxa\Queue\RetryPolicyInterface;
use Myxa\Queue\WorkerInterface;
use Throwable;

final class QueueWorker implements WorkerInterface
{
    private bool $running = true;

    public function __construct(
        private readonly QueueInterface $queue,
        private readonly RetryPolicyInterface $retryPolicy,
        private readonly int $sleepSeconds = 3,
        private readonly int $maxIdleCycles = 0,
    ) {
    }

    public function run(?string $queue = null): int
    {
        return $this->work($queue);
    }

    public function work(
        ?string $queue = null,
        bool $once = false,
        ?int $sleepSeconds = null,
        ?int $maxJobs = null,
        ?int $maxIdleCycles = null,
    ): int {
        $sleepSeconds ??= $this->sleepSeconds;
        $maxIdleCycles ??= $this->maxIdleCycles;
        $processed = 0;
        $idleCycles = 0;
        $this->running = true;

        while ($this->running) {
            $message = $this->queue->pop($queue);

            if (!$message instanceof JobEnvelope) {
                $idleCycles++;

                if ($once || ($maxIdleCycles > 0 && $idleCycles >= $maxIdleCycles)) {
                    break;
                }

                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }

                continue;
            }

            $idleCycles = 0;
            $this->process($message);
            $processed++;

            if ($once || ($maxJobs !== null && $maxJobs > 0 && $processed >= $maxJobs)) {
                break;
            }
        }

        return 0;
    }

    public function process(JobEnvelope $message): void
    {
        try {
            $message->job->handle();
            $this->queue->ack($message);
        } catch (Throwable $error) {
            if ($this->retryPolicy->shouldRetry($message, $error)) {
                $this->queue->release($message, $this->retryPolicy->delaySeconds($message, $error));

                return;
            }

            $this->queue->fail($message, $error);
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }
}
