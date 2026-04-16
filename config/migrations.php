<?php

declare(strict_types=1);

return [
    'repository_table' => 'migrations',
    'paths' => [
        'migrations' => database_path('migrations'),
        'schema' => database_path('schema'),
    ],
    'models' => [
        'path' => app_path('Models'),
        'namespace' => 'App\\Models',
    ],
];
