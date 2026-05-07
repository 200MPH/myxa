<?php

declare(strict_types=1);

return [
    'path' => database_path('seeders'),
    'namespace' => 'Database\\Seeders',
    'default' => 'Database\\Seeders\\DatabaseSeeder',
    'connections' => [
        'database' => (string) env('DB_CONNECTION', 'mysql'),
        'redis' => (string) env('REDIS_CONNECTION', 'default'),
        'mongo' => null,
    ],
];
