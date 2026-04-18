<?php

declare(strict_types=1);

return [
    'default' => (string) env('QUEUE_CONNECTION', 'file'),
    'default_queue' => (string) env('QUEUE_NAME', 'default'),
    'visibility_timeout_seconds' => (int) env('QUEUE_VISIBILITY_TIMEOUT', 60),
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('queue'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => (string) env('QUEUE_REDIS_CONNECTION', (string) env('REDIS_CONNECTION', 'default')),
            'prefix' => (string) env('QUEUE_REDIS_PREFIX', 'queue:'),
        ],
    ],
    'worker' => [
        'sleep_seconds' => (int) env('QUEUE_WORKER_SLEEP', 3),
        'max_idle_cycles' => (int) env('QUEUE_WORKER_MAX_IDLE', 0),
        'default_max_attempts' => (int) env('QUEUE_WORKER_MAX_ATTEMPTS', 3),
        'backoff_seconds' => (int) env('QUEUE_WORKER_BACKOFF', 30),
    ],
];
