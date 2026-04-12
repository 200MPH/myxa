<?php

declare(strict_types=1);

return [
    'default' => (string) env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => (string) env('DB_DRIVER', 'mysql'),
            'host' => (string) env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => (string) env('DB_DATABASE', 'myxa'),
            'username' => (string) env('DB_USERNAME', 'myxa'),
            'password' => (string) env('DB_PASSWORD', 'secret'),
            'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
            'options' => [],
        ],
    ],
];
