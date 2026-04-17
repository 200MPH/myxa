<?php

declare(strict_types=1);

namespace App\Events;

use App\Support\Naming;
use RuntimeException;

final class EventScaffolder
{
    private string $eventsPath;

    /**
     * Generate application event classes under the Events directory.
     */
    public function __construct(?string $eventsPath = null)
    {
        $this->eventsPath = $eventsPath ?? app_path('Events');
    }

    /**
     * Create a new event class file.
     *
     * @return array{path: string, class: class-string}
     */
    public function make(string $name): array
    {
        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);

        if ($className === '') {
            throw new RuntimeException('Event class name could not be resolved.');
        }

        $path = $this->eventPath($namespace, $className);
        $fqcn = $namespace . '\\' . $className;

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Event file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create events directory [%s].', $directory));
        }

        $source = $this->source($namespace, $className);

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write event file [%s].', $path));
        }

        return [
            'path' => $path,
            'class' => $fqcn,
        ];
    }

    /**
     * Normalize slash-delimited event input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Event name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize an event name into an App\Events namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = 'App\\Events';
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Event namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    /**
     * Derive an event file path for a class living under App\Events.
     */
    public function eventPath(string $namespace, string $className): string
    {
        $rootNamespace = 'App\\Events';

        if (!str_starts_with($namespace, $rootNamespace)) {
            throw new RuntimeException(sprintf('Event namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = trim(substr($namespace, strlen($rootNamespace)), '\\');
        $path = rtrim($this->eventsPath, DIRECTORY_SEPARATOR);

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
            'use Myxa\Events\AbstractEvent;',
            '',
            sprintf('final readonly class %s extends AbstractEvent', $className),
            '{',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }
}
