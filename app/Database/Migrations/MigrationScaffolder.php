<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Support\Naming;
use RuntimeException;

final class MigrationScaffolder
{
    /**
     * Generate timestamped migration files using project naming conventions.
     */
    public function __construct(private readonly MigrationConfig $config)
    {
    }

    /**
     * Create a new migration stub for a generic, create-table, or alter-table migration.
     */
    public function make(
        string $name,
        ?string $createTable = null,
        ?string $table = null,
        ?string $className = null,
        ?string $connection = null,
    ): string {
        $slug = Naming::snake($name);
        $className ??= Naming::studly($name);

        if ($slug === '' || $className === '') {
            throw new RuntimeException('Migration name could not be normalized.');
        }

        $source = $this->source($className, $createTable, $table, $connection);

        return $this->write($slug, $source);
    }

    /**
     * Persist raw migration source code as a timestamped migration file.
     */
    public function write(string $name, string $source): string
    {
        $directory = $this->config->migrationsPath();

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create migrations directory [%s].', $directory));
        }

        $path = rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . date('Y_m_d_His')
            . '_'
            . Naming::snake($name)
            . '.php';

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Migration file [%s] already exists.', $path));
        }

        if (@file_put_contents($path, $source) === false) {
            throw new RuntimeException(sprintf('Unable to write migration file [%s].', $path));
        }

        return $path;
    }

    private function source(?string $className, ?string $createTable, ?string $table, ?string $connection): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use Myxa\Database\Migrations\Migration;',
            'use Myxa\Database\Schema\Blueprint;',
            'use Myxa\Database\Schema\Schema;',
            '',
            sprintf('final class %s extends Migration', $className),
            '{',
        ];

        if ($connection !== null && trim($connection) !== '') {
            $lines[] = '    public function connectionName(): ?string';
            $lines[] = '    {';
            $lines[] = sprintf("        return '%s';", $connection);
            $lines[] = '    }';
            $lines[] = '';
        }

        $lines[] = '    public function up(Schema $schema): void';
        $lines[] = '    {';

        if ($createTable !== null) {
            $lines[] = sprintf("        \$schema->create('%s', function (Blueprint \$table): void {", $createTable);
            $lines[] = '            $table->id();';
            $lines[] = '            $table->timestamps();';
            $lines[] = '        });';
        } elseif ($table !== null) {
            $lines[] = sprintf("        \$schema->table('%s', function (Blueprint \$table): void {", $table);
            $lines[] = '            // TODO: Define the forward table changes.';
            $lines[] = '        });';
        } else {
            $lines[] = '        // TODO: Define the forward migration.';
        }

        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public function down(Schema $schema): void';
        $lines[] = '    {';

        if ($createTable !== null) {
            $lines[] = sprintf("        \$schema->drop('%s');", $createTable);
        } elseif ($table !== null) {
            $lines[] = sprintf("        \$schema->table('%s', function (Blueprint \$table): void {", $table);
            $lines[] = '            // TODO: Reverse the table changes.';
            $lines[] = '        });';
        } else {
            $lines[] = '        // TODO: Define the rollback migration.';
        }

        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
