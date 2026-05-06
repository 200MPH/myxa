<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Config\ConfigRepository;

final class SeederConfig
{
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    public function seedersPath(): string
    {
        return (string) $this->config->get('seeders.path', database_path('seeders'));
    }

    public function namespace(): string
    {
        return trim((string) $this->config->get('seeders.namespace', 'Database\\Seeders'), '\\');
    }

    /**
     * @return class-string<Seeder>
     */
    public function defaultSeeder(): string
    {
        $default = (string) $this->config->get(
            'seeders.default',
            $this->namespace() . '\\DatabaseSeeder',
        );

        /** @var class-string<Seeder> $default */
        return trim($default, '\\');
    }

    public function databaseConnection(): ?string
    {
        return $this->stringValue('seeders.connections.database')
            ?? $this->stringValue('database.default');
    }

    public function redisConnection(): ?string
    {
        return $this->stringValue('seeders.connections.redis')
            ?? $this->stringValue('services.redis.default');
    }

    public function mongoConnection(): ?string
    {
        return $this->stringValue('seeders.connections.mongo');
    }

    private function stringValue(string $key): ?string
    {
        $value = $this->config->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
