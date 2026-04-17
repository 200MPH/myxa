<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Naming;
use RuntimeException;

final class DataScaffolder
{
    private string $dataPath;

    /**
     * Generate DTO-style data classes under the Data directory.
     */
    public function __construct(?string $dataPath = null)
    {
        $this->dataPath = $dataPath ?? app_path('Data');
    }

    /**
     * Create a new data class file.
     *
     * @return array{path: string, class: class-string}
     */
    public function make(string $name): array
    {
        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);
        $className = str_ends_with($className, 'Data') ? $className : $className . 'Data';

        if ($className === 'Data') {
            throw new RuntimeException('Data class name could not be resolved.');
        }

        $path = $this->dataClassPath($namespace, $className);
        $fqcn = $namespace . '\\' . $className;

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Data file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create data directory [%s].', $directory));
        }

        $source = $this->source($namespace, $className);

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write data file [%s].', $path));
        }

        return [
            'path' => $path,
            'class' => $fqcn,
        ];
    }

    /**
     * Normalize slash-delimited data input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Data name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize a data class name into an App\Data namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = 'App\\Data';
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Data namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    /**
     * Derive a data file path for a class living under App\Data.
     */
    public function dataClassPath(string $namespace, string $className): string
    {
        $rootNamespace = 'App\\Data';

        if (!str_starts_with($namespace, $rootNamespace)) {
            throw new RuntimeException(sprintf('Data namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = trim(substr($namespace, strlen($rootNamespace)), '\\');
        $path = rtrim($this->dataPath, DIRECTORY_SEPARATOR);

        if ($relativeNamespace !== '') {
            $path .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);
        }

        return $path . DIRECTORY_SEPARATOR . $className . '.php';
    }

    private function source(string $namespace, string $className): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
            'use JsonSerializable;',
            '',
            sprintf('final readonly class %s implements JsonSerializable', $className),
            '{',
            '    /**',
            '     * @param array<string, mixed> $attributes',
            '     */',
            '    public function __construct(public array $attributes = [])',
            '    {',
            '    }',
            '',
            '    /**',
            '     * @param array<string, mixed> $attributes',
            '     */',
            '    public static function fromArray(array $attributes): self',
            '    {',
            '        return new self($attributes);',
            '    }',
            '',
            '    /**',
            '     * @return array<string, mixed>',
            '     */',
            '    public function toArray(): array',
            '    {',
            '        return $this->attributes;',
            '    }',
            '',
            '    /**',
            '     * @return array<string, mixed>',
            '     */',
            '    public function jsonSerialize(): array',
            '    {',
            '        return $this->toArray();',
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }
}
