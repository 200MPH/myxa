<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ConfigRepository;
use Myxa\Redis\Connection\PhpRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use Myxa\Redis\RedisServiceProvider as FrameworkRedisServiceProvider;
use Myxa\Support\ServiceProvider;

final class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app()->make(ConfigRepository::class);
        $connectionConfigurations = $config->get('services.redis.connections', []);

        if (!is_array($connectionConfigurations) || $connectionConfigurations === []) {
            return;
        }

        $connections = [];

        foreach ($connectionConfigurations as $alias => $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $host = (string) ($connection['host'] ?? '');
            if ($host === '') {
                continue;
            }

            $port = is_numeric($connection['port'] ?? null) ? (int) $connection['port'] : 6379;
            $timeout = is_numeric($connection['timeout'] ?? null) ? (float) $connection['timeout'] : 2.0;
            $database = is_numeric($connection['database'] ?? null) ? (int) $connection['database'] : 0;
            $password = $connection['password'] ?? null;

            $connections[(string) $alias] = new RedisConnection(new PhpRedisStore(
                host: $host,
                port: $port,
                timeout: $timeout,
                database: $database,
                password: is_string($password) && $password !== '' ? $password : null,
            ));
        }

        if ($connections === []) {
            return;
        }

        $defaultConnection = (string) $config->get('services.redis.default', array_key_first($connections));

        $this->app()->register(new FrameworkRedisServiceProvider($connections, $defaultConnection));
    }
}
