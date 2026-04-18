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
        's3' => [
            'driver' => 's3',
            'bucket' => (string) env('AWS_BUCKET', ''),
            'region' => (string) env('AWS_DEFAULT_REGION', 'us-east-1'),
            'access_key' => (string) env('AWS_ACCESS_KEY_ID', ''),
            'secret_key' => (string) env('AWS_SECRET_ACCESS_KEY', ''),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'endpoint' => env('AWS_ENDPOINT'),
            'path_style' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
    ],
];
