<?php

declare(strict_types=1);

return [
    'default' => (string) env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => (string) env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 3306),
            'database' => (string) env('DB_DATABASE', 'myxa'),
            'username' => (string) env('DB_USERNAME', 'myxa'),
            'password' => (string) env('DB_PASSWORD', 'secret'),
            'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
            'options' => [],
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => (string) env('DB_PGSQL_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PGSQL_PORT', 5432),
            'database' => (string) env('DB_PGSQL_DATABASE', 'myxa'),
            'username' => (string) env('DB_PGSQL_USERNAME', 'postgres'),
            'password' => (string) env('DB_PGSQL_PASSWORD', ''),
            'charset' => (string) env('DB_PGSQL_CHARSET', 'utf8'),
            'options' => [],
        ],
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => (string) env('DB_SQLITE_DATABASE', database_path('database.sqlite')),
            'options' => [],
        ],
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => (string) env('DB_SQLSRV_HOST', '127.0.0.1'),
            'port' => (int) env('DB_SQLSRV_PORT', 1433),
            'database' => (string) env('DB_SQLSRV_DATABASE', 'myxa'),
            'username' => (string) env('DB_SQLSRV_USERNAME', 'sa'),
            'password' => (string) env('DB_SQLSRV_PASSWORD', ''),
            'charset' => (string) env('DB_SQLSRV_CHARSET', 'utf8'),
            'options' => [],
        ],
    ],
];
