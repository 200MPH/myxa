<?php

declare(strict_types=1);

return [
    'redis' => [
        'default' => (string) env('REDIS_CONNECTION', 'default'),
        'connections' => [
            'default' => [
                'host' => (string) env('REDIS_HOST', '127.0.0.1'),
                'port' => (int) env('REDIS_PORT', 6379),
                'database' => (int) env('REDIS_DB', 0),
                'password' => env('REDIS_PASSWORD'),
                'timeout' => (float) env('REDIS_TIMEOUT', 2.0),
            ],
        ],
    ],
    'mongo' => [
        'default' => (string) env('MONGO_CONNECTION', 'default'),
        'connections' => [
            'default' => [
                'uri' => (string) env('MONGO_URI', 'mongodb://127.0.0.1:27017'),
                'database' => (string) env('MONGO_DATABASE', 'myxa'),
                'uri_options' => [],
                'driver_options' => [],
            ],
        ],
    ],
];
