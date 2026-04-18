<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Config\ConfigRepository;
use BadMethodCallException;

final class Config
{
    private static ?ConfigRepository $repository = null;

    public static function setRepository(ConfigRepository $repository): void
    {
        self::$repository = $repository;
    }

    public static function clearRepository(): void
    {
        self::$repository = null;
    }

    public static function getRepository(): ConfigRepository
    {
        if (!self::$repository instanceof ConfigRepository) {
            throw new \RuntimeException('Config facade has not been initialized.');
        }

        return self::$repository;
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        return self::getRepository()->get($key, $default);
    }

    public static function has(string $key): bool
    {
        return self::getRepository()->has($key);
    }

    public static function set(string $key, mixed $value): void
    {
        self::getRepository()->set($key, $value);
    }

    public static function all(): array
    {
        return self::getRepository()->all();
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (!method_exists(self::getRepository(), $name)) {
            throw new BadMethodCallException(sprintf('Config facade method "%s" is not supported.', $name));
        }

        return self::getRepository()->{$name}(...$arguments);
    }
}
