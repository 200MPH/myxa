<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Support\Naming;
use RuntimeException;

final class SeederScaffolder
{
    public function __construct(private readonly SeederConfig $config)
    {
    }

    public function make(string $name): string
    {
        $class = $this->normalizeClass($name);
        $path = $this->pathForClass($class);

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Seeder file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create seeders directory [%s].', $directory));
        }

        if (@file_put_contents($path, $this->source($class)) === false) {
            throw new RuntimeException(sprintf('Unable to write seeder file [%s].', $path));
        }

        return $path;
    }

    /**
     * @return class-string<Seeder>
     */
    private function normalizeClass(string $name): string
    {
        $normalized = trim(trim(str_replace('/', '\\', $name)), '\\');
        if ($normalized === '') {
            throw new RuntimeException('Seeder name could not be resolved.');
        }

        $namespace = Naming::namespace($normalized, $this->config->namespace()) ?? $this->config->namespace();
        $className = Naming::classBasename($normalized);
        if (!str_ends_with($className, 'Seeder')) {
            $className .= 'Seeder';
        }

        if (!str_starts_with($namespace, $this->config->namespace())) {
            $namespace = $this->config->namespace() . '\\' . trim($namespace, '\\');
        }

        /** @var class-string<Seeder> $class */
        $class = $namespace . '\\' . $className;

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
     * @param class-string<Seeder> $class
     */
    private function source(string $class): string
    {
        $namespace = Naming::namespace($class, $this->config->namespace()) ?? $this->config->namespace();
        $className = Naming::classBasename($class);

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
            'use App\\Database\\Seeders\\SeedContext;',
            'use App\\Database\\Seeders\\Seeder;',
            '',
            sprintf('final class %s extends Seeder', $className),
            '{',
            '    public function run(SeedContext $context): void',
            '    {',
            '        // Example: $context->database()->insert(...);',
            '    }',
            '}',
            '',
        ]);
    }
}
