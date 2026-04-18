<?php

declare(strict_types=1);

namespace App\Queue;

trait Quable
{
    public function queue(): ?string
    {
        return null;
    }

    public function delaySeconds(): int
    {
        return 0;
    }

    public function maxAttempts(): int
    {
        return 3;
    }
}
