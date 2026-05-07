<?php

declare(strict_types=1);

namespace App\Database\Seeders;

final readonly class LoadedSeeder
{
    /**
     * @param class-string<Seeder> $class
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $class,
    ) {
    }
}
