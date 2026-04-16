<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Myxa\Database\Migrations\Migration;

final readonly class LoadedMigration
{
    /**
     * Carry the resolved metadata and instantiated object for one migration file.
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $class,
        public Migration $instance,
    ) {
    }
}
