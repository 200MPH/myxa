<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Config\ConfigRepository;

final class MigrationConfig
{
    /**
     * Wrap migration-related configuration lookups behind one typed API.
     */
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    /**
     * Return the table name used to track applied migrations.
     */
    public function repositoryTable(): string
    {
        return (string) $this->config->get('migrations.repository_table', 'migrations');
    }

    /**
     * Return the directory where migration PHP files are stored.
     */
    public function migrationsPath(): string
    {
        return (string) $this->config->get('migrations.paths.migrations', database_path('migrations'));
    }

    /**
     * Return the directory where schema snapshot JSON files are stored.
     */
    public function schemaPath(): string
    {
        return (string) $this->config->get('migrations.paths.schema', database_path('schema'));
    }

    /**
     * Return the directory where generated model classes are written.
     */
    public function modelsPath(): string
    {
        return (string) $this->config->get('migrations.models.path', app_path('Models'));
    }

    /**
     * Return the default namespace used for generated models.
     */
    public function modelNamespace(): string
    {
        return (string) $this->config->get('migrations.models.namespace', 'App\\Models');
    }

    /**
     * Return the default database connection alias for migration operations.
     */
    public function defaultConnection(): string
    {
        return (string) $this->config->get('database.default', 'default');
    }

    /**
     * Build the expected snapshot filename for a connection.
     */
    public function snapshotPath(?string $connection = null): string
    {
        $connection ??= $this->defaultConnection();

        return rtrim($this->schemaPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $connection
            . '.json';
    }
}
