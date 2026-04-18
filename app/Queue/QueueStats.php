<?php

declare(strict_types=1);

namespace App\Queue;

final readonly class QueueStats
{
    public function __construct(
        public string $queue,
        public int $ready,
        public int $delayed,
        public int $reserved,
        public int $failed,
    ) {
    }
}
