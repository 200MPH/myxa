<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Database\Connection\PdoConnectionConfig;
use Myxa\Database\DatabaseServiceProvider as FrameworkDatabaseServiceProvider;
use Myxa\Support\ServiceProvider;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $connectionConfigurations = $config->get('database.connections', []);

        if (!is_array($connectionConfigurations) || $connectionConfigurations === []) {
            return;
        }

        $connections = [];

        foreach ($connectionConfigurations as $alias => $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $driver = (string) ($connection['driver'] ?? $connection['engine'] ?? '');
            $database = (string) ($connection['database'] ?? '');
            $host = (string) ($connection['host'] ?? '');

            if ($driver === '' || $database === '' || $host === '') {
                continue;
            }

            $port = $connection['port'] ?? null;
            $charset = $connection['charset'] ?? null;
            $username = $connection['username'] ?? null;
            $password = $connection['password'] ?? null;
            $options = is_array($connection['options'] ?? null) ? $connection['options'] : [];

            $connections[(string) $alias] = new PdoConnectionConfig(
                engine: $driver,
                database: $database,
                host: $host,
                port: is_numeric($port) ? (int) $port : null,
                charset: is_string($charset) ? $charset : null,
                username: is_string($username) ? $username : null,
                password: is_string($password) ? $password : null,
                options: $options,
            );
        }

        if ($connections === []) {
            return;
        }

        $defaultConnection = (string) $config->get('database.default', array_key_first($connections));

        $this->app()->register(new FrameworkDatabaseServiceProvider($connections, $defaultConnection));
    }
}
