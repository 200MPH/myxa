<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Naming;
use RuntimeException;

final class MiddlewareScaffolder
{
    private string $middlewarePath;

    /**
     * Generate HTTP middleware classes under the Middleware directory.
     */
    public function __construct(?string $middlewarePath = null)
    {
        $this->middlewarePath = $middlewarePath ?? app_path('Http/Middleware');
    }

    /**
     * Create a new middleware class file.
     *
     * @return array{path: string, class: class-string}
     */
    public function make(string $name): array
    {
        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);
        $className = str_ends_with($className, 'Middleware') ? $className : $className . 'Middleware';

        if ($className === 'Middleware') {
            throw new RuntimeException('Middleware class name could not be resolved.');
        }

        $path = $this->middlewareClassPath($namespace, $className);
        $fqcn = $namespace . '\\' . $className;

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Middleware file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create middleware directory [%s].', $directory));
        }

        $source = $this->source($namespace, $className);

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write middleware file [%s].', $path));
        }

        return [
            'path' => $path,
            'class' => $fqcn,
        ];
    }

    /**
     * Normalize slash-delimited middleware input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Middleware name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize a middleware name into an App\Http\Middleware namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = 'App\\Http\\Middleware';
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App' || $namespace === 'App\\Http') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Middleware namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    /**
     * Derive a middleware file path for a class living under App\Http\Middleware.
     */
    public function middlewareClassPath(string $namespace, string $className): string
    {
        $rootNamespace = 'App\\Http\\Middleware';

        if (!str_starts_with($namespace, $rootNamespace)) {
            throw new RuntimeException(sprintf('Middleware namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = trim(substr($namespace, strlen($rootNamespace)), '\\');
        $path = rtrim($this->middlewarePath, DIRECTORY_SEPARATOR);

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
            'use Closure;',
            'use Myxa\Http\Request;',
            'use Myxa\Middleware\MiddlewareInterface;',
            'use Myxa\Routing\RouteDefinition;',
            '',
            sprintf('final class %s implements MiddlewareInterface', $className),
            '{',
            '    public function handle(Request $request, Closure $next, RouteDefinition $route): mixed',
            '    {',
            '        return $next();',
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }
}
