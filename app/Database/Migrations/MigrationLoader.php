<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Myxa\Database\Migrations\Migration;
use ReflectionClass;
use RuntimeException;

final class MigrationLoader
{
    /**
     * Discover and instantiate migration classes from the configured directory.
     */
    public function __construct(private readonly MigrationConfig $config)
    {
    }

    /**
     * Return every migration file path in execution order.
     *
     * @return list<string>
     */
    public function discoverPaths(): array
    {
        $pattern = rtrim($this->config->migrationsPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php';
        $paths = glob($pattern) ?: [];
        sort($paths);

        return array_values($paths);
    }

    /**
     * Load every configured migration file into executable objects.
     *
     * @return list<LoadedMigration>
     */
    public function loadAll(): array
    {
        return array_map(fn (string $path): LoadedMigration => $this->loadPath($path), $this->discoverPaths());
    }

    /**
     * Load one migration file and resolve the concrete migration class declared inside it.
     */
    public function loadPath(string $path): LoadedMigration
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || !is_file($resolvedPath)) {
            throw new RuntimeException(sprintf('Migration file [%s] was not found.', $path));
        }

        require_once $resolvedPath;

        $class = $this->resolveMigrationClass($resolvedPath);
        $instance = new $class();

        return new LoadedMigration(
            name: basename($resolvedPath, '.php'),
            path: $resolvedPath,
            class: $class,
            instance: $instance,
        );
    }

    /**
     * Resolve either a bare migration filename or an absolute path to a real file.
     */
    public function resolvePath(string $nameOrPath): string
    {
        if (is_file($nameOrPath)) {
            return (string) realpath($nameOrPath);
        }

        $candidate = rtrim($this->config->migrationsPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim($nameOrPath, DIRECTORY_SEPARATOR);

        $resolved = realpath($candidate);

        if ($resolved === false || !is_file($resolved)) {
            throw new RuntimeException(sprintf('Migration [%s] could not be resolved.', $nameOrPath));
        }

        return $resolved;
    }

    /**
     * @return class-string<Migration>
     */
    private function resolveMigrationClass(string $path): string
    {
        $resolvedPath = realpath($path);

        foreach (array_reverse(get_declared_classes()) as $class) {
            if (!is_subclass_of($class, Migration::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            if (realpath((string) $reflection->getFileName()) !== $resolvedPath) {
                continue;
            }

            /** @var class-string<Migration> $class */
            return $class;
        }

        throw new RuntimeException(sprintf(
            'Migration file [%s] did not declare a concrete migration class.',
            $path,
        ));
    }
}
