<?php

declare(strict_types=1);

namespace App\Database\Seeders;

abstract class Seeder
{
    /**
     * Seed application data using whichever backing stores the app needs.
     */
    abstract public function run(SeedContext $context): void;
}
