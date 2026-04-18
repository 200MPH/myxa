<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Support\Naming;
use Myxa\Database\DatabaseManager;
use RuntimeException;

final class ModelScaffolder
{
    /**
     * Generate model classes from conventions, live tables, or migration files.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly MigrationConfig $config,
        private readonly MigrationLoader $loader,
    ) {
    }

    /**
     * Create a model file using either a blank scaffold or a reverse-engineered source.
     */
    public function make(
        string $name,
        ?string $table = null,
        ?string $fromTable = null,
        ?string $fromMigration = null,
        ?string $connection = null,
    ): string {
        $name = $this->normalizeName($name);
        $namespace = $this->normalizeNamespace($name);
        $className = Naming::classBasename($name);

        if (($fromTable !== null ? 1 : 0) + ($fromMigration !== null ? 1 : 0) > 1) {
            throw new RuntimeException('Choose only one model source: --from-table or --from-migration.');
        }

        $source = match (true) {
            $fromTable !== null => $this->database
                ->schema($connection)
                ->modelFromTable($fromTable, $className, $namespace),
            $fromMigration !== null => $this->database
                ->schema($connection)
                ->modelFromMigration(
                    $this->loader->loadPath($this->loader->resolvePath($fromMigration))->instance,
                    $className,
                    namespace: $namespace,
                ),
            default => $this->skeleton($className, $namespace, $table),
        };

        return $this->write($namespace, $className, $source);
    }

    /**
     * Normalize slash-delimited model input into PHP namespace format.
     */
    public function normalizeName(string $name): string
    {
        $normalized = trim(str_replace('/', '\\', $name), '\\');

        if ($normalized === '') {
            throw new RuntimeException('Model name could not be resolved.');
        }

        return $normalized;
    }

    /**
     * Normalize a model name into the configured model namespace.
     */
    public function normalizeNamespace(string $name): string
    {
        $rootNamespace = trim($this->config->modelNamespace(), '\\');
        $namespace = Naming::namespace($name, $rootNamespace) ?? $rootNamespace;

        if ($namespace === 'App') {
            return $rootNamespace;
        }

        if (str_starts_with($namespace, $rootNamespace)) {
            return $namespace;
        }

        if (str_starts_with($namespace, 'App\\')) {
            throw new RuntimeException(sprintf('Model namespace must live under %s.', $rootNamespace));
        }

        return $rootNamespace . '\\' . trim($namespace, '\\');
    }

    private function write(?string $namespace, string $className, string $source): string
    {
        $directory = $this->modelDirectory($namespace);

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create models directory [%s].', $directory));
        }

        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Model file [%s] already exists.', $path));
        }

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write model file [%s].', $path));
        }

        return $path;
    }

    private function modelDirectory(?string $namespace): string
    {
        $rootNamespace = trim($this->config->modelNamespace(), '\\');
        $directory = rtrim($this->config->modelsPath(), DIRECTORY_SEPARATOR);
        $namespace = trim((string) $namespace, '\\');

        if ($namespace === '' || $namespace === $rootNamespace) {
            return $directory;
        }

        if (!str_starts_with($namespace, $rootNamespace . '\\')) {
            throw new RuntimeException(sprintf('Model namespace must live under %s.', $rootNamespace));
        }

        $relativeNamespace = substr($namespace, strlen($rootNamespace) + 1);

        return $directory . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);
    }

    private function skeleton(string $className, ?string $namespace, ?string $table): string
    {
        $table ??= Naming::pluralize(Naming::snake($className));

        $lines = ['<?php', '', 'declare(strict_types=1);', ''];

        if ($namespace !== null && $namespace !== '') {
            $lines[] = sprintf('namespace %s;', $namespace);
            $lines[] = '';
        }

        $lines[] = 'use Myxa\Database\Model\Model;';
        $lines[] = '';
        $lines[] = sprintf('final class %s extends Model', $className);
        $lines[] = '{';
        $lines[] = sprintf("    protected string \$table = '%s';", $table);
        $lines[] = '';
        $lines[] = '    protected ?int $id = null;';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
