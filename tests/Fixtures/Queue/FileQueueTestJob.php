<?php

declare(strict_types=1);

namespace Test\Fixtures\Queue;

use App\Queue\Quable;
use Myxa\Queue\JobInterface;

final class FileQueueTestJob implements JobInterface
{
    use Quable;

    public function handle(): void
    {
    }
}
