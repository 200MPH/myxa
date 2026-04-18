<?php

declare(strict_types=1);

namespace App\Queue;

final readonly class FailedJobRecord
{
    public function __construct(
        public string $id,
        public string $queue,
        public string $jobClass,
        public int $attempts,
        public string $failedAt,
        public ?string $errorMessage = null,
    ) {
    }
}
