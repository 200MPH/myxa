<?php

declare(strict_types=1);

namespace App\Support;

final class Naming
{
    /**
     * Return the trailing class name from a fully qualified class string.
     */
    public static function classBasename(string $class): string
    {
        $class = trim($class, '\\');

        return str_contains($class, '\\')
            ? (string) substr($class, ((int) strrpos($class, '\\')) + 1)
            : $class;
    }

    /**
     * Return the namespace portion of a class name, or the provided default when missing.
     */
    public static function namespace(string $class, ?string $default = null): ?string
    {
        $class = trim($class, '\\');

        if (!str_contains($class, '\\')) {
            return $default;
        }

        return (string) substr($class, 0, (int) strrpos($class, '\\'));
    }

    /**
     * Normalize arbitrary text into a StudlyCase class name.
     */
    public static function studly(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', trim($value)) ?? '';
        $value = str_replace(' ', '', ucwords(strtolower($value)));

        return preg_replace('/^[0-9]+/', '', $value) ?? '';
    }

    /**
     * Normalize arbitrary text or class names into snake_case.
     */
    public static function snake(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['\\', '-', ' '], '_', $value);
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        $value = strtolower($value);

        return trim((string) preg_replace('/_+/', '_', $value), '_');
    }

    /**
     * Apply a small set of English pluralization rules for generated table names.
     */
    public static function pluralize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/[^aeiou]y$/i', $value) === 1) {
            return substr($value, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/i', $value) === 1) {
            return $value . 'es';
        }

        return $value . 's';
    }
}
