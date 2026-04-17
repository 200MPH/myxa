<?php

declare(strict_types=1);

return [
    'name' => (string) env('APP_NAME', 'Myxa App'),
    'env' => (string) env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => (string) env('APP_URL', 'https://' . (string) env('APP_HOST', 'myxa.localhost')),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
    'log' => [
        'path' => storage_path('logs/app.log'),
    ],
    'providers' => [
        App\Providers\ConfigServiceProvider::class,
        App\Providers\FrameworkServiceProvider::class,
        App\Providers\AppServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\CacheServiceProvider::class,
        App\Providers\RoutesServiceProvider::class,
        App\Providers\DatabaseServiceProvider::class,
        App\Providers\StorageServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\RedisServiceProvider::class,
    ],
];
