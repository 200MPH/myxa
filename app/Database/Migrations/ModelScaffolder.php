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
        $namespace = Naming::namespace($name, $this->config->modelNamespace());
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

        return $this->write($className, $source);
    }

    private function write(string $className, string $source): string
    {
        $directory = $this->config->modelsPath();

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
