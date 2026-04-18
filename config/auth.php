<?php

declare(strict_types=1);

return [
    'session' => [
        'driver' => (string) env('AUTH_SESSION_DRIVER', 'file'),
        'cookie' => (string) env('AUTH_SESSION_COOKIE', 'myxa_session'),
        'lifetime' => (int) env('AUTH_SESSION_LIFETIME', 1209600),
        'http_only' => true,
        'same_site' => (string) env('AUTH_SESSION_SAME_SITE', 'Lax'),
        'secure' => (bool) env('AUTH_SESSION_SECURE', env('APP_ENV', 'local') === 'production'),
        'length' => (int) env('AUTH_SESSION_LENGTH', 64),
        'path' => (string) env('AUTH_SESSION_PATH', storage_path('sessions')),
        'redis' => [
            'connection' => (string) env('AUTH_SESSION_REDIS_CONNECTION', (string) env('REDIS_CONNECTION', 'default')),
            'prefix' => (string) env('AUTH_SESSION_REDIS_PREFIX', 'session:'),
        ],
    ],
    'tokens' => [
        'length' => (int) env('AUTH_TOKEN_LENGTH', 40),
        'default_name' => (string) env('AUTH_TOKEN_NAME', 'cli'),
        'default_scopes' => array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            explode(',', (string) env('AUTH_TOKEN_SCOPES', '*')),
        ), static fn (string $scope): bool => $scope !== '')),
    ],
];
