<?php

declare(strict_types=1);

return [
    'default' => (string) env('CACHE_STORE', 'local'),
    'stores' => [
        'local' => [
            'driver' => 'file',
            'path' => storage_path('cache'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => (string) env('CACHE_REDIS_CONNECTION', (string) env('REDIS_CONNECTION', 'default')),
            'prefix' => (string) env('CACHE_REDIS_PREFIX', 'cache:'),
        ],
    ],
    'routes' => [
        'enabled' => (bool) env('ROUTE_CACHE', (string) env('APP_ENV', 'local') === 'production'),
        'path' => storage_path('cache/framework/routes.php'),
    ],
];
