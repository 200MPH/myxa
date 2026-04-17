<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Naming;
use Myxa\Events\EventInterface;
use RuntimeException;

final class ListenerScaffolder
{
    private string $listenersPath;
    private string $providerPath;

    /**
     * Generate event listener classes under the Listeners directory.
     */
    public function __construct(?string $listenersPath = null, ?string $providerPath = null)
    {
        $this->listenersPath = $listenersPath ?? app_path('Listeners');
        $this->providerPath = $providerPath ?? app_path('Providers/EventServiceProvider.php');
    }

    /**
     * Create a new listener class file.
     *
     * @return array{path: string, class: class-string, event: class-string<EventInterface>|null}
     */
    public function make(string $name, ?string $event = null): array
    {
        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);
        $className = str_ends_with($className, 'Listener') ? $className : $className . 'Listener';

        if ($className === 'Listener') {
            throw new RuntimeException('Listener class name could not be resolved.');
        }

        $eventClass = $this->normalizeEventClass($event);
        $path = $this->listenerPath($namespace, $className);
        $fqcn = $namespace . '\\' . $className;

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Listener file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create listeners directory [%s].', $directory));
        }

        $source = $this->source($namespace, $className, $eventClass);

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write listener file [%s].', $path));
        }

        if ($eventClass !== null) {
            $this->registerInProvider($eventClass, $fqcn);
        }

        return [
            'path' => $path,
            'class' => $fqcn,
            'event' => $eventClass,
        ];
    }

    /**
     * Normalize slash-delimited listener input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Listener name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize a listener name into an App\Listeners namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = 'App\\Listeners';
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Listener namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    /**
     * Resolve a listener target event class, defaulting local shorthand names into App\Events.
     *
     * @return class-string<EventInterface>|null
     */
    public function normalizeEventClass(?string $event): ?string
    {
        if ($event === null) {
            return null;
        }

        $normalized = trim(str_replace('/', '\\', $event), '\\');

        if ($normalized === '') {
            return null;
        }

        if (!str_contains($normalized, '\\')) {
            return 'App\\Events\\' . $normalized;
        }

        if (str_starts_with($normalized, 'App\\') || str_starts_with($normalized, 'Myxa\\')) {
            return $normalized;
        }

        return 'App\\Events\\' . $normalized;
    }

    /**
     * Derive a listener file path for a class living under App\Listeners.
     */
    public function listenerPath(string $namespace, string $className): string
    {
        $rootNamespace = 'App\\Listeners';

        if (!str_starts_with($namespace, $rootNamespace)) {
            throw new RuntimeException(sprintf('Listener namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = trim(substr($namespace, strlen($rootNamespace)), '\\');
        $path = rtrim($this->listenersPath, DIRECTORY_SEPARATOR);

        if ($relativeNamespace !== '') {
            $path .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);
        }

        return $path . DIRECTORY_SEPARATOR . $className . '.php';
    }

    /**
     * @param class-string<EventInterface>|null $eventClass
     */
    private function source(string $namespace, string $className, ?string $eventClass): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
        ];

        if ($eventClass !== null) {
            $lines[] = sprintf('use %s;', $eventClass);
        }

        $lines[] = 'use Myxa\Events\EventHandlerInterface;';
        $lines[] = 'use Myxa\Events\EventInterface;';
        $lines[] = '';
        $lines[] = sprintf('final class %s implements EventHandlerInterface', $className);
        $lines[] = '{';

        if ($eventClass !== null) {
            $eventShortName = Naming::classBasename($eventClass);
            $lines[] = sprintf('    /** @param %s $event */', $eventShortName);
        }

        $lines[] = '    public function handle(EventInterface $event): void';
        $lines[] = '    {';

        if ($eventClass !== null) {
            $eventShortName = Naming::classBasename($eventClass);
            $lines[] = sprintf('        if (!$event instanceof %s) {', $eventShortName);
            $lines[] = '            return;';
            $lines[] = '        }';
            $lines[] = '';
        }

        $lines[] = '        // Handle the event.';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Register a generated listener in the app event provider when an event target is known.
     *
     * @param class-string<EventInterface> $eventClass
     * @param class-string $listenerClass
     */
    private function registerInProvider(string $eventClass, string $listenerClass): void
    {
        if (!is_file($this->providerPath)) {
            return;
        }

        $source = @file_get_contents($this->providerPath);
        if (!is_string($source)) {
            throw new RuntimeException(sprintf('Unable to read event provider [%s].', $this->providerPath));
        }

        $updated = $this->withRegisteredListener($source, $eventClass, $listenerClass);

        if ($updated === $source) {
            return;
        }

        if (@file_put_contents($this->providerPath, $updated) === false) {
            throw new RuntimeException(sprintf('Unable to update event provider [%s].', $this->providerPath));
        }
    }

    /**
     * Insert a listener mapping into the listeners() array source.
     *
     * @param class-string<EventInterface> $eventClass
     * @param class-string $listenerClass
     */
    private function withRegisteredListener(string $source, string $eventClass, string $listenerClass): string
    {
        $methodPattern = '/(?P<prefix>protected function listeners\(\): array\s*\{\s*return \[)(?P<body>.*?)(?P<suffix>\s*\];\s*\})/s';

        if (preg_match($methodPattern, $source, $matches) !== 1) {
            throw new RuntimeException('Unable to locate listeners() array in the event provider.');
        }

        $body = $matches['body'];
        $updatedBody = $this->mergeListenerIntoBody($body, $eventClass, $listenerClass);

        if ($updatedBody === $body) {
            return $source;
        }

        $updated = preg_replace_callback(
            $methodPattern,
            static fn (array $matches): string => $matches['prefix'] . $updatedBody . $matches['suffix'],
            $source,
            1,
        );

        return is_string($updated) ? $updated : $source;
    }

    /**
     * @param class-string<EventInterface> $eventClass
     * @param class-string $listenerClass
     */
    private function mergeListenerIntoBody(string $body, string $eventClass, string $listenerClass): string
    {
        $eventKey = '\\' . ltrim($eventClass, '\\') . '::class';
        $listenerKey = '\\' . ltrim($listenerClass, '\\') . '::class';

        $eventBlockPattern = '/(?P<prefix>^\s*' . preg_quote($eventKey, '/') . " => \\[\n)(?P<listeners>.*?)(?P<suffix>^\\s*\\],\\s*$)/ms";

        if (preg_match($eventBlockPattern, $body, $matches) === 1) {
            $listeners = $matches['listeners'];

            if (str_contains($listeners, $listenerKey)) {
                return $body;
            }

            $updatedBlock = $matches['prefix']
                . $listeners
                . '                ' . $listenerKey . ",\n"
                . $matches['suffix'];

            $updatedBody = preg_replace_callback(
                $eventBlockPattern,
                static fn (): string => $updatedBlock,
                $body,
                1,
            );

            return is_string($updatedBody) ? $updatedBody : $body;
        }

        $trimmedBody = trim($body);
        $entry = "\n"
            . '            ' . $eventKey . " => [\n"
            . '                ' . $listenerKey . ",\n"
            . "            ],\n";

        if ($trimmedBody === '') {
            return $entry . '        ';
        }

        return rtrim($body) . $entry . '        ';
    }
}
