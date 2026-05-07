<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Myxa\Application;
use Myxa\Database\DatabaseManager;
use Myxa\Mongo\MongoManager;
use Myxa\Redis\RedisManager;
use RuntimeException;

final class SeedContext
{
    public function __construct(
        private readonly Application $app,
        private readonly ?string $databaseConnection = null,
        private readonly ?string $redisConnection = null,
        private readonly ?string $mongoConnection = null,
        private readonly bool $truncate = false,
    ) {
    }

    public function app(): Application
    {
        return $this->app;
    }

    public function database(?string $connection = null): DatabaseManager
    {
        $database = $this->app->make(DatabaseManager::class);
        if (!$database instanceof DatabaseManager) {
            throw new RuntimeException(sprintf(
                'Container entry [%s] must be a database manager.',
                DatabaseManager::class,
            ));
        }

        $connection ??= $this->databaseConnection;
        if ($connection !== null) {
            $database->setDefaultConnection($connection);
        }

        $database->connection();

        return $database;
    }

    public function databaseConnection(): ?string
    {
        return $this->databaseConnection;
    }

    public function shouldTruncate(): bool
    {
        return $this->truncate;
    }

    /**
     * Delete all rows from the named SQL tables on the selected connection.
     *
     * @param string|list<string> $tables
     */
    public function truncateTables(string|array $tables, ?string $connection = null): void
    {
        foreach ($this->normalizeTableNames($tables) as $table) {
            $database = $this->database($connection);
            $database->statement(
                sprintf('DELETE FROM %s', $table),
                [],
                $database->getDefaultConnection(),
            );
        }
    }

    public function redis(?string $connection = null): RedisManager
    {
        $redis = $this->app->make(RedisManager::class);
        if (!$redis instanceof RedisManager) {
            throw new RuntimeException(sprintf('Container entry [%s] must be a Redis manager.', RedisManager::class));
        }

        $connection ??= $this->redisConnection;
        if ($connection !== null) {
            $redis->setDefaultConnection($connection);
        }

        $redis->connection();

        return $redis;
    }

    public function redisConnection(): ?string
    {
        return $this->redisConnection;
    }

    public function mongo(?string $connection = null): MongoManager
    {
        $mongo = $this->app->make(MongoManager::class);
        if (!$mongo instanceof MongoManager) {
            throw new RuntimeException(sprintf('Container entry [%s] must be a Mongo manager.', MongoManager::class));
        }

        $connection ??= $this->mongoConnection;
        if ($connection !== null) {
            $mongo->setDefaultConnection($connection);
        }

        $mongo->connection();

        return $mongo;
    }

    public function mongoConnection(): ?string
    {
        return $this->mongoConnection;
    }

    public function make(string $abstract): mixed
    {
        return $this->app->make($abstract);
    }

    /**
     * @param string|list<string> $tables
     * @return list<string>
     */
    private function normalizeTableNames(string|array $tables): array
    {
        $tables = is_string($tables) ? [$tables] : $tables;
        $normalized = [];

        foreach ($tables as $table) {
            $table = trim($table);
            if ($table === '') {
                throw new RuntimeException('Seeder truncate table name cannot be empty.');
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $table) !== 1) {
                throw new RuntimeException(sprintf('Seeder truncate table name [%s] is not valid.', $table));
            }

            $normalized[] = $table;
        }

        return $normalized;
    }
}
