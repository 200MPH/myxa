<?php

declare(strict_types=1);

namespace App\Console;

use App\Support\Naming;
use RuntimeException;

final class CommandScaffolder
{
    private string $commandsPath;

    /**
     * Generate console command classes under the Commands directory.
     */
    public function __construct(?string $commandsPath = null)
    {
        $this->commandsPath = $commandsPath ?? app_path('Console/Commands');
    }

    /**
     * Create a new command class file that will be auto-discovered by the kernel.
     *
     * @return array{path: string, class: class-string, command: string}
     */
    public function make(string $name, ?string $commandName = null, ?string $description = null): array
    {
        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);
        $className = str_ends_with($className, 'Command') ? $className : $className . 'Command';

        if ($className === 'Command') {
            throw new RuntimeException('Command class name could not be resolved.');
        }

        $commandName ??= $this->defaultCommandName($className);
        $description ??= sprintf('Describe the [%s] command.', $commandName);

        $path = $this->commandPath($namespace, $className);
        $fqcn = $namespace . '\\' . $className;

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Command file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create commands directory [%s].', $directory));
        }

        $source = $this->source($namespace, $className, $commandName, $description);

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write command file [%s].', $path));
        }

        return [
            'path' => $path,
            'class' => $fqcn,
            'command' => $commandName,
        ];
    }

    /**
     * Normalize slash-delimited command input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Command name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize a command name into an App\Console\Commands namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = 'App\\Console\\Commands';
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App' || $namespace === 'App\\Console') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Command namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    /**
     * Derive a command file path for a class living under App\Console\Commands.
     */
    public function commandPath(string $namespace, string $className): string
    {
        $rootNamespace = 'App\\Console\\Commands';

        if (!str_starts_with($namespace, $rootNamespace)) {
            throw new RuntimeException(sprintf('Command namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = trim(substr($namespace, strlen($rootNamespace)), '\\');

        $path = rtrim($this->commandsPath, DIRECTORY_SEPARATOR);
        if ($relativeNamespace !== '') {
            $path .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);
        }

        return $path . DIRECTORY_SEPARATOR . $className . '.php';
    }

    private function defaultCommandName(string $className): string
    {
        $base = preg_replace('/Command$/', '', $className) ?? $className;
        $base = Naming::snake($base);

        if ($base === '') {
            throw new RuntimeException('Command name could not be inferred from the class name.');
        }

        return str_replace('_', ':', $base);
    }

    private function source(string $namespace, string $className, string $commandName, string $description): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
            'use Myxa\Console\Command;',
            '',
            sprintf('final class %s extends Command', $className),
            '{',
            '    public function name(): string',
            '    {',
            sprintf("        return '%s';", $this->escapeSingleQuoted($commandName)),
            '    }',
            '',
            '    public function description(): string',
            '    {',
            sprintf("        return '%s';", $this->escapeSingleQuoted($description)),
            '    }',
            '',
            '    protected function handle(): int',
            '    {',
            sprintf(
                "        \$this->info('Command [%s] is ready.')->icon();",
                $this->escapeSingleQuoted($commandName),
            ),
            '',
            '        return 0;',
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }

    private function escapeSingleQuoted(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }
}
