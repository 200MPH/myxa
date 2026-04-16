<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Naming;
use RuntimeException;

final class ControllerScaffolder
{
    private string $controllersPath;

    /**
     * Generate HTTP controller classes under the Controllers directory.
     */
    public function __construct(?string $controllersPath = null)
    {
        $this->controllersPath = $controllersPath ?? app_path('Http/Controllers');
    }

    /**
     * Create a new controller class file.
     *
     * @return array{path: string, class: class-string, style: string}
     */
    public function make(string $name, bool $invokable = false, bool $resource = false): array
    {
        if ($invokable && $resource) {
            throw new RuntimeException('Choose either an invokable controller or a resource controller, not both.');
        }

        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);
        $className = str_ends_with($className, 'Controller') ? $className : $className . 'Controller';

        if ($className === 'Controller') {
            throw new RuntimeException('Controller class name could not be resolved.');
        }

        $path = $this->controllerPath($namespace, $className);
        $fqcn = $namespace . '\\' . $className;

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Controller file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create controllers directory [%s].', $directory));
        }

        $source = match (true) {
            $resource => $this->resourceSource($namespace, $className),
            $invokable => $this->invokableSource($namespace, $className),
            default => $this->defaultSource($namespace, $className),
        };

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write controller file [%s].', $path));
        }

        return [
            'path' => $path,
            'class' => $fqcn,
            'style' => $resource ? 'resource' : ($invokable ? 'invokable' : 'controller'),
        ];
    }

    /**
     * Normalize slash-delimited controller input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Controller name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize a controller name into an App\Http\Controllers namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = 'App\\Http\\Controllers';
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App' || $namespace === 'App\\Http') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Controller namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    /**
     * Derive a controller file path for a class living under App\Http\Controllers.
     */
    public function controllerPath(string $namespace, string $className): string
    {
        $rootNamespace = 'App\\Http\\Controllers';

        if (!str_starts_with($namespace, $rootNamespace)) {
            throw new RuntimeException(sprintf('Controller namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = trim(substr($namespace, strlen($rootNamespace)), '\\');
        $path = rtrim($this->controllersPath, DIRECTORY_SEPARATOR);

        if ($relativeNamespace !== '') {
            $path .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);
        }

        return $path . DIRECTORY_SEPARATOR . $className . '.php';
    }

    private function defaultSource(string $namespace, string $className): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
            'use Myxa\Http\Request;',
            'use Myxa\Http\Response;',
            '',
            sprintf('final class %s', $className),
            '{',
            '    public function index(Request $request): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.']);",
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }

    private function invokableSource(string $namespace, string $className): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
            'use Myxa\Http\Request;',
            'use Myxa\Http\Response;',
            '',
            sprintf('final class %s', $className),
            '{',
            '    public function __invoke(Request $request): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.']);",
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }

    private function resourceSource(string $namespace, string $className): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
            'use Myxa\Http\Request;',
            'use Myxa\Http\Response;',
            '',
            sprintf('final class %s', $className),
            '{',
            '    public function index(Request $request): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.']);",
            '    }',
            '',
            '    public function create(): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.']);",
            '    }',
            '',
            '    public function store(Request $request): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.']);",
            '    }',
            '',
            '    public function show(string $id): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.', 'id' => \$id]);",
            '    }',
            '',
            '    public function edit(string $id): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.', 'id' => \$id]);",
            '    }',
            '',
            '    public function update(Request $request, string $id): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.', 'id' => \$id]);",
            '    }',
            '',
            '    public function destroy(string $id): Response',
            '    {',
            "        return (new Response())->json(['message' => 'Not implemented yet.', 'id' => \$id]);",
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }
}
