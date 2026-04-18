<?php

declare(strict_types=1);

namespace Test\Fixtures\Queue;

use App\Queue\Quable;
use Myxa\Queue\JobInterface;
use RuntimeException;

final readonly class QueueCommandRetryableJob implements JobInterface
{
    use Quable;

    public function __construct(
        private string $attemptPath,
        private string $processedPath,
    ) {
    }

    public function handle(): void
    {
        if (!is_file($this->attemptPath)) {
            file_put_contents($this->attemptPath, 'failed-once');

            throw new RuntimeException('retryable job failed once');
        }

        file_put_contents($this->processedPath, 'processed-after-retry');
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
