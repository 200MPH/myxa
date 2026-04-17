<?php

declare(strict_types=1);

return [
    'default_store' => (string) env('RATE_LIMIT_STORE', 'file'),
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('rate-limit'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => (string) env('RATE_LIMIT_REDIS_CONNECTION', (string) env('REDIS_CONNECTION', 'default')),
            'prefix' => (string) env('RATE_LIMIT_REDIS_PREFIX', 'rate-limit:'),
        ],
    ],
    'presets' => [
        'api' => [
            'max_attempts' => (int) env('RATE_LIMIT_API_MAX_ATTEMPTS', 60),
            'decay_seconds' => (int) env('RATE_LIMIT_API_DECAY_SECONDS', 60),
            'prefix' => 'api',
        ],
        'login' => [
            'max_attempts' => (int) env('RATE_LIMIT_LOGIN_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('RATE_LIMIT_LOGIN_DECAY_SECONDS', 60),
            'prefix' => 'login',
        ],
        'uploads' => [
            'max_attempts' => (int) env('RATE_LIMIT_UPLOADS_MAX_ATTEMPTS', 20),
            'decay_seconds' => (int) env('RATE_LIMIT_UPLOADS_DECAY_SECONDS', 60),
            'prefix' => 'uploads',
        ],
    ],
];
