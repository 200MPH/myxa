<?php

declare(strict_types=1);

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $basePath = defined('MYXA_BASE_PATH') ? MYXA_BASE_PATH : dirname(__DIR__);

        if ($path === '') {
            return $basePath;
        }

        return $basePath . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('route_path')) {
    function route_path(string $path = ''): string
    {
        return base_path('routes' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        if ($value === null) {
            $value = getenv($key);
        }

        if ($value === false) {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        return \App\Support\Facades\Config::get($key, $default);
    }
}

if (!function_exists('myxa_request_expects_json')) {
    function myxa_request_expects_json(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json') || str_contains($accept, '+json')) {
            return true;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json') || str_contains($contentType, '+json')) {
            return true;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || $path === '/api'
            || str_starts_with($path, '/api/');
    }
}

if (!function_exists('myxa_emergency_log')) {
    function myxa_emergency_log(string|\Throwable $error, ?\Throwable $secondary = null): void
    {
        $lines = [];

        $appendThrowable = static function (string $label, \Throwable $throwable) use (&$lines): void {
            $lines[] = sprintf(
                '%s: %s in %s:%d',
                $label,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : $throwable::class,
                $throwable->getFile(),
                $throwable->getLine(),
            );
            $lines[] = sprintf('Type: %s', $throwable::class);
            $lines[] = sprintf('Trace: %s', $throwable->getTraceAsString());
        };

        if ($error instanceof \Throwable) {
            $appendThrowable('Unhandled exception', $error);
        } else {
            $lines[] = $error;
        }

        if ($secondary !== null) {
            $appendThrowable('Failure while handling exception', $secondary);
        }

        $message = '[myxa emergency] ' . implode(PHP_EOL, $lines);

        try {
            $written = @error_log($message);
            if ($written === false) {
                @file_put_contents('php://stderr', $message . PHP_EOL, FILE_APPEND);
            }
        } catch (\Throwable) {
            // Last-resort logging must never crash the request.
        }
    }
}

if (!function_exists('myxa_emit_emergency_response')) {
    function myxa_emit_emergency_response(int $statusCode = 500, ?bool $expectsJson = null): void
    {
        $expectsJson ??= myxa_request_expects_json();

        if (!headers_sent()) {
            http_response_code($statusCode);
            header(sprintf(
                'Content-Type: %s',
                $expectsJson ? 'application/json; charset=UTF-8' : 'text/plain; charset=UTF-8',
            ), true);
        }

        if ($expectsJson) {
            echo sprintf(
                '{"error":{"type":"server_error","message":"Server Error","status":%d}}',
                $statusCode,
            );

            return;
        }

        echo 'Server Error';
    }
}
