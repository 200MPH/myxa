<?php

declare(strict_types=1);

namespace Test\Fixtures\Queue;

use App\Queue\Quable;
use Myxa\Queue\JobInterface;
use RuntimeException;

final class QueueCommandFailingJob implements JobInterface
{
    use Quable;

    public function handle(): void
    {
        throw new RuntimeException('job failed intentionally');
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
