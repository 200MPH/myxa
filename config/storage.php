<?php

declare(strict_types=1);

return [
    'default' => (string) env('STORAGE_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
        ],
        'db' => [
            'driver' => 'database',
            'file_table' => (string) env('STORAGE_DB_FILE_TABLE', 'files'),
            'content_table' => (string) env('STORAGE_DB_CONTENT_TABLE', 'file_contents'),
        ],
    ],
];
