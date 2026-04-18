<?php

declare(strict_types=1);

namespace App\Console;

use Myxa\Console\CommandInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

final class CommandDiscovery
{
    /**
     * Discover command classes from the Commands directory tree.
     */
    public function __construct(
        private readonly string $commandsPath = '',
        private readonly string $namespace = 'App\\Console\\Commands',
    ) {
    }

    /**
     * Return concrete command classes found under the configured commands directory.
     *
     * @return list<class-string<CommandInterface>>
     */
    public function discover(): array
    {
        $commandsPath = $this->commandsPath !== '' ? $this->commandsPath : app_path('Console/Commands');

        if (!is_dir($commandsPath)) {
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $commandsPath,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        sort($paths);

        $commands = [];

        foreach ($paths as $path) {
            $class = $this->classFromPath($commandsPath, $path);

            require_once $path;

            if (!str_ends_with($class, 'Command')) {
                continue;
            }

            if (!class_exists($class)) {
                throw new RuntimeException(sprintf('Command class [%s] was not found for file [%s].', $class, $path));
            }

            if (!is_subclass_of($class, CommandInterface::class)) {
                throw new RuntimeException(sprintf(
                    'Command class [%s] must implement [%s].',
                    $class,
                    CommandInterface::class,
                ));
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var class-string<CommandInterface> $class */
            $commands[] = $class;
        }

        return $commands;
    }

    private function classFromPath(string $commandsPath, string $path): string
    {
        $relativePath = substr($path, strlen(rtrim($commandsPath, DIRECTORY_SEPARATOR)) + 1);
        $relativeClass = str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            $relativePath,
        );

        return trim($this->namespace, '\\') . '\\' . $relativeClass;
    }
}
