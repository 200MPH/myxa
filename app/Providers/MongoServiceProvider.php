<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Mongo\Connection\MongoConnection;
use Myxa\Mongo\MongoServiceProvider as FrameworkMongoServiceProvider;
use Myxa\Support\ServiceProvider;

final class MongoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $connectionConfigurations = $config->get('services.mongo.connections', []);

        if (!is_array($connectionConfigurations) || $connectionConfigurations === []) {
            return;
        }

        $connections = [];

        foreach ($connectionConfigurations as $alias => $connection) {
            if (!is_string($alias) || !is_array($connection)) {
                continue;
            }

            $uri = trim((string) ($connection['uri'] ?? ''));
            $database = trim((string) ($connection['database'] ?? ''));

            if ($uri === '' || $database === '') {
                continue;
            }

            $uriOptions = $connection['uri_options'] ?? [];
            $driverOptions = $connection['driver_options'] ?? [];

            $connections[$alias] = static fn (): MongoConnection => MongoConnection::fromUri(
                uri: $uri,
                database: $database,
                uriOptions: is_array($uriOptions) ? $uriOptions : [],
                driverOptions: is_array($driverOptions) ? $driverOptions : [],
            );
        }

        if ($connections === []) {
            return;
        }

        $defaultConnection = (string) $config->get('services.mongo.default', array_key_first($connections));

        $this->app()->register(new FrameworkMongoServiceProvider($connections, $defaultConnection));
    }
}
