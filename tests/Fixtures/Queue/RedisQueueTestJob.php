<?php

declare(strict_types=1);

namespace Test\Fixtures\Queue;

use Myxa\Queue\JobInterface;

final class RedisQueueTestJob implements JobInterface
{
    public function handle(): void
    {
    }
}
