<?php

declare(strict_types=1);

namespace App\Foundation;

final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            if (
                $name === ''
                || array_key_exists($name, $_ENV)
                || array_key_exists($name, $_SERVER)
                || getenv($name) !== false
            ) {
                continue;
            }

            if (
                $value !== ''
                && (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
                )
            ) {
                $value = substr($value, 1, -1);
            } else {
                $commentOffset = strpos($value, ' #');
                if ($commentOffset !== false) {
                    $value = rtrim(substr($value, 0, $commentOffset));
                }
            }

            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
