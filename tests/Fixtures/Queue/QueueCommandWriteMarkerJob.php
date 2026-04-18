<?php

declare(strict_types=1);

namespace Test\Fixtures\Queue;

use App\Queue\Quable;
use Myxa\Queue\JobInterface;

final readonly class QueueCommandWriteMarkerJob implements JobInterface
{
    use Quable;

    public function __construct(private string $markerPath)
    {
    }

    public function handle(): void
    {
        file_put_contents($this->markerPath, 'processed');
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
