<?php

declare(strict_types=1);

namespace App\Database\Seeders;

trait ShouldTruncate
{
    /**
     * @return string|list<string>
     */
    abstract protected function tablesToTruncate(): string|array;

    public function truncate(SeedContext $context): void
    {
        $context->truncateTables($this->tablesToTruncate());
    }
}
