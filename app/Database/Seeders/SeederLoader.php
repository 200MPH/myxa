<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use ReflectionClass;
use RuntimeException;

final class SeederLoader
{
    public function __construct(private readonly SeederConfig $config)
    {
    }

    public function loadDefault(): LoadedSeeder
    {
        return $this->load($this->config->defaultSeeder());
    }

    public function load(string $nameOrClassOrPath): LoadedSeeder
    {
        $target = trim($nameOrClassOrPath);
        if ($target === '') {
            throw new RuntimeException('Seeder name could not be resolved.');
        }

        if (class_exists($target) && is_subclass_of($target, Seeder::class)) {
            return $this->loadClass($target);
        }

        if (is_file($target)) {
            return $this->loadPath($target);
        }

        $class = $this->normalizeClass($target);
        $path = $this->pathForClass($class);

        return $this->loadPath($path);
    }

    public function loadPath(string $path): LoadedSeeder
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || !is_file($resolvedPath)) {
            throw new RuntimeException(sprintf('Seeder file [%s] was not found.', $path));
        }

        require_once $resolvedPath;

        $class = $this->resolveSeederClass($resolvedPath);

        return new LoadedSeeder(
            name: basename($resolvedPath, '.php'),
            path: $resolvedPath,
            class: $class,
        );
    }

    /**
     * @param class-string<Seeder> $class
     */
    private function loadClass(string $class): LoadedSeeder
    {
        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract()) {
            throw new RuntimeException(sprintf('Seeder class [%s] is abstract.', $class));
        }

        $path = (string) $reflection->getFileName();

        return new LoadedSeeder(
            name: $reflection->getShortName(),
            path: $path,
            class: $class,
        );
    }

    /**
     * @return class-string<Seeder>
     */
    private function normalizeClass(string $name): string
    {
        $class = trim(str_replace('/', '\\', $name), '\\');
        if (!str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        if (!str_contains($class, '\\')) {
            $class = $this->config->namespace() . '\\' . $class;
        }

        /** @var class-string<Seeder> $class */
        return $class;
    }

    /**
     * @param class-string<Seeder> $class
     */
    private function pathForClass(string $class): string
    {
        $namespace = $this->config->namespace();
        if (!str_starts_with($class, $namespace . '\\')) {
            throw new RuntimeException(sprintf('Seeder class [%s] must live under %s.', $class, $namespace));
        }

        $relative = substr($class, strlen($namespace) + 1);

        return rtrim($this->config->seedersPath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relative)
            . '.php';
    }

    /**
     * @return class-string<Seeder>
     */
    private function resolveSeederClass(string $path): string
    {
        $resolvedPath = realpath($path);

        foreach (array_reverse(get_declared_classes()) as $class) {
            if (!is_subclass_of($class, Seeder::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            if (realpath((string) $reflection->getFileName()) !== $resolvedPath) {
                continue;
            }

            /** @var class-string<Seeder> $class */
            return $class;
        }

        throw new RuntimeException(sprintf(
            'Seeder file [%s] did not declare a concrete seeder class.',
            $path,
        ));
    }
}
