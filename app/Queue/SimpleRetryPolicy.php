<?php

declare(strict_types=1);

namespace App\Queue;

use Myxa\Queue\JobEnvelope;
use Myxa\Queue\RetryPolicyInterface;
use Throwable;

final readonly class SimpleRetryPolicy implements RetryPolicyInterface
{
    public function __construct(
        private int $defaultMaxAttempts = 3,
        private int $baseDelaySeconds = 30,
    ) {
    }

    public function shouldRetry(JobEnvelope $message, Throwable $error): bool
    {
        $maxAttempts = JobMetadata::maxAttempts($message->job, $this->defaultMaxAttempts);

        return ($message->attempts + 1) < $maxAttempts;
    }

    public function delaySeconds(JobEnvelope $message, Throwable $error): int
    {
        $multiplier = max(1, $message->attempts + 1);

        return max(0, $this->baseDelaySeconds * $multiplier);
    }
}
