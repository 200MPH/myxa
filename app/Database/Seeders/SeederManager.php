<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Myxa\Application;
use RuntimeException;

final class SeederManager
{
    public function __construct(
        private readonly Application $app,
        private readonly SeederConfig $config,
        private readonly SeederLoader $loader,
    ) {
    }

    /**
     * @return array{name: string, class: class-string<Seeder>, path: string}
     */
    public function seed(
        ?string $seeder = null,
        ?string $databaseConnection = null,
        ?string $redisConnection = null,
        ?string $mongoConnection = null,
        bool $truncate = false,
    ): array {
        $loaded = $seeder === null || trim($seeder) === ''
            ? $this->loader->loadDefault()
            : $this->loader->load($seeder);

        $instance = $this->app->make($loaded->class);
        if (!$instance instanceof Seeder) {
            throw new RuntimeException(sprintf('Seeder class [%s] must extend %s.', $loaded->class, Seeder::class));
        }

        $context = new SeedContext(
            $this->app,
            $databaseConnection ?? $this->config->databaseConnection(),
            $redisConnection ?? $this->config->redisConnection(),
            $mongoConnection ?? $this->config->mongoConnection(),
            $truncate,
        );

        if ($truncate && is_callable([$instance, 'truncate'])) {
            $instance->truncate($context);
        }

        $instance->run($context);

        return [
            'name' => $loaded->name,
            'class' => $loaded->class,
            'path' => $loaded->path,
        ];
    }
}
