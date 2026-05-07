<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Support\Naming;
use Myxa\Database\DatabaseManager;
use RuntimeException;

final class ReverseSeedScaffolder
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly SeederConfig $config,
    ) {
    }

    /**
     * @param list<string> $tables
     * @param list<string> $ignoreRelations
     * @param list<string> $excludeColumns
     * @param list<string> $maskColumns
     * @param array<string, string> $overrides
     */
    public function make(
        ?string $table = null,
        array $tables = [],
        int $limit = 20,
        ?string $connection = null,
        ?string $className = null,
        array $ignoreRelations = [],
        array $excludeColumns = [],
        array $maskColumns = [],
        array $overrides = [],
        ?string $password = null,
    ): string {
        $seedConnection = $connection;

        if ($limit < 1) {
            throw new RuntimeException('Reverse seed limit must be at least 1.');
        }

        if ($table !== null && $tables !== []) {
            throw new RuntimeException('Use either --table or --tables, not both.');
        }

        $connection ??= $this->config->databaseConnection();
        $table = $table !== null ? $this->normalizeIdentifier($table) : null;
        $tables = array_map($this->normalizeIdentifier(...), $tables);
        $ignoreRelations = array_map($this->normalizeIdentifier(...), $ignoreRelations);
        $excludeColumns = array_map($this->normalizeIdentifier(...), $excludeColumns);
        $maskColumns = array_map($this->normalizeIdentifier(...), $maskColumns);
        $overrides = $this->normalizeOverrides($overrides);

        $reverse = $this->database->schema($connection)->reverseEngineer();
        $availableTables = $reverse->tables();
        $selectedTables = $this->selectTables($table, $tables, $availableTables, $connection, $ignoreRelations);
        $selectedTables = $this->sortForInsertOrder($selectedTables, $connection);
        $rowsByTable = $table !== null
            ? $this->rowsForRootTable($table, $selectedTables, $limit, $connection)
            : $this->rowsForTables($selectedTables, $limit, $connection);

        $rowsByTable = $this->transformRows($rowsByTable, $excludeColumns, $maskColumns, $overrides, $password);

        $className ??= 'ReverseDatabaseSeeder';
        $class = $this->normalizeClass($className);
        $path = $this->pathForClass($class);

        if (is_file($path)) {
            throw new RuntimeException(sprintf('Seeder file [%s] already exists.', $path));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create seeders directory [%s].', $directory));
        }

        if (@file_put_contents($path, $this->source($class, $rowsByTable, $password, $seedConnection)) === false) {
            throw new RuntimeException(sprintf('Unable to write seeder file [%s].', $path));
        }

        return $path;
    }

    /**
     * @param list<string> $tables
     * @param list<string> $availableTables
     * @param list<string> $ignoreRelations
     * @return list<string>
     */
    private function selectTables(
        ?string $table,
        array $tables,
        array $availableTables,
        ?string $connection,
        array $ignoreRelations,
    ): array {
        if ($table === null && $tables === []) {
            return $availableTables;
        }

        if ($tables !== []) {
            $this->ensureTablesExist($tables, $availableTables);

            return $tables;
        }

        $this->ensureTablesExist([$table], $availableTables);
        $ignored = array_flip($ignoreRelations);
        $selected = [$table => true];

        foreach ($availableTables as $candidate) {
            if (isset($ignored[$candidate])) {
                continue;
            }

            $foreignKeys = $this->database->schema($connection)->reverseEngineer()->table($candidate)->foreignKeys();

            foreach ($foreignKeys as $foreignKey) {
                $referencedTable = $foreignKey->referencedTable();

                if (isset($ignored[$referencedTable])) {
                    continue;
                }

                if ($candidate === $table || $referencedTable === $table) {
                    $selected[$candidate] = true;
                }

                if ($candidate === $table) {
                    $selected[$referencedTable] = true;
                }
            }
        }

        return array_values(array_intersect($availableTables, array_keys($selected)));
    }

    /**
     * @param list<string> $tables
     * @param list<string> $availableTables
     */
    private function ensureTablesExist(array $tables, array $availableTables): void
    {
        $available = array_flip($availableTables);

        foreach ($tables as $table) {
            if (!isset($available[$table])) {
                throw new RuntimeException(sprintf('Database table [%s] was not found.', $table));
            }
        }
    }

    /**
     * @param list<string> $tables
     * @return list<string>
     */
    private function sortForInsertOrder(array $tables, ?string $connection): array
    {
        $selected = array_flip($tables);
        $dependencies = [];

        foreach ($tables as $table) {
            $dependencies[$table] = [];

            $foreignKeys = $this->database->schema($connection)->reverseEngineer()->table($table)->foreignKeys();

            foreach ($foreignKeys as $foreignKey) {
                if (isset($selected[$foreignKey->referencedTable()])) {
                    $dependencies[$table][] = $foreignKey->referencedTable();
                }
            }
        }

        $sorted = [];
        $visiting = [];
        $visited = [];

        foreach ($tables as $table) {
            $this->visitTable($table, $dependencies, $visiting, $visited, $sorted);
        }

        return $sorted;
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param array<string, true> $visiting
     * @param array<string, true> $visited
     * @param list<string> $sorted
     */
    private function visitTable(
        string $table,
        array $dependencies,
        array &$visiting,
        array &$visited,
        array &$sorted,
    ): void {
        if (isset($visited[$table])) {
            return;
        }

        if (isset($visiting[$table])) {
            $visited[$table] = true;
            $sorted[] = $table;

            return;
        }

        $visiting[$table] = true;

        foreach ($dependencies[$table] ?? [] as $dependency) {
            $this->visitTable($dependency, $dependencies, $visiting, $visited, $sorted);
        }

        unset($visiting[$table]);
        $visited[$table] = true;
        $sorted[] = $table;
    }

    /**
     * @param list<string> $tables
     * @return array<string, list<array<string, mixed>>>
     */
    private function rowsForTables(array $tables, int $limit, ?string $connection): array
    {
        $rows = [];

        foreach ($tables as $table) {
            $rows[$table] = $this->selectRows($table, $limit, $connection);
        }

        return $rows;
    }

    /**
     * @param list<string> $selectedTables
     * @return array<string, list<array<string, mixed>>>
     */
    private function rowsForRootTable(string $rootTable, array $selectedTables, int $limit, ?string $connection): array
    {
        $rows = [];
        $rootRows = $this->selectRows($rootTable, $limit, $connection);
        $rows[$rootTable] = $rootRows;

        foreach ($selectedTables as $table) {
            if ($table === $rootTable) {
                continue;
            }

            $relatedRows = [];
            $schema = $this->database->schema($connection)->reverseEngineer()->table($table);

            foreach ($schema->foreignKeys() as $foreignKey) {
                if ($foreignKey->referencedTable() !== $rootTable) {
                    continue;
                }

                $relatedRows = array_merge($relatedRows, $this->selectRelatedRows(
                    $table,
                    $foreignKey->columns()[0] ?? '',
                    $rootRows,
                    $foreignKey->referencedColumns()[0] ?? '',
                    $limit,
                    $connection,
                ));
            }

            $foreignKeys = $this->database->schema($connection)->reverseEngineer()->table($rootTable)->foreignKeys();

            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey->referencedTable() !== $table) {
                    continue;
                }

                $relatedRows = array_merge($relatedRows, $this->selectRelatedRows(
                    $table,
                    $foreignKey->referencedColumns()[0] ?? '',
                    $rootRows,
                    $foreignKey->columns()[0] ?? '',
                    $limit,
                    $connection,
                ));
            }

            $rows[$table] = $this->uniqueRows($relatedRows);
        }

        return $this->sortRowsByTableOrder($rows, $selectedTables);
    }

    /**
     * @param list<array<string, mixed>> $sourceRows
     * @return list<array<string, mixed>>
     */
    private function selectRelatedRows(
        string $table,
        string $column,
        array $sourceRows,
        string $sourceColumn,
        int $limit,
        ?string $connection,
    ): array {
        if ($column === '' || $sourceColumn === '') {
            return [];
        }

        $values = [];
        foreach ($sourceRows as $row) {
            $value = $row[$sourceColumn] ?? null;
            if (is_scalar($value) || $value === null) {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values, SORT_REGULAR));
        if ($values === []) {
            return [];
        }

        $query = $this->database->query($connection)
            ->select('*')
            ->from($table)
            ->whereIn($column, $values)
            ->limit($limit);

        return $this->database->select($query->toSql(), $query->getBindings(), $connection);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectRows(string $table, int $limit, ?string $connection): array
    {
        $query = $this->database->query($connection)
            ->select('*')
            ->from($table)
            ->limit($limit);

        return $this->database->select($query->toSql(), $query->getBindings(), $connection);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueRows(array $rows): array
    {
        $unique = [];

        foreach ($rows as $row) {
            $unique[serialize($row)] = $row;
        }

        return array_values($unique);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rows
     * @param list<string> $tables
     * @return array<string, list<array<string, mixed>>>
     */
    private function sortRowsByTableOrder(array $rows, array $tables): array
    {
        $sorted = [];

        foreach ($tables as $table) {
            $sorted[$table] = $rows[$table] ?? [];
        }

        return $sorted;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rowsByTable
     * @param list<string> $excludeColumns
     * @param list<string> $maskColumns
     * @param array<string, string> $overrides
     * @return array<string, list<array<string, mixed>>>
     */
    private function transformRows(
        array $rowsByTable,
        array $excludeColumns,
        array $maskColumns,
        array $overrides,
        ?string $password,
    ): array {
        $exclude = array_flip($excludeColumns);
        $mask = array_flip($maskColumns);
        $transformed = [];

        foreach ($rowsByTable as $table => $rows) {
            $transformed[$table] = [];

            foreach ($rows as $index => $row) {
                foreach (array_keys($row) as $column) {
                    if (isset($exclude[$column])) {
                        unset($row[$column]);
                        continue;
                    }

                    if ($password !== null && $this->isPasswordColumn($column)) {
                        $row[$column] = ['__password_hash__' => true];
                        continue;
                    }

                    if (array_key_exists($column, $overrides)) {
                        $row[$column] = $overrides[$column];
                        continue;
                    }

                    if (isset($mask[$column]) && $row[$column] !== null) {
                        $row[$column] = str_contains($column, 'email')
                            ? sprintf('masked-%s-%d@example.test', $table, $index + 1)
                            : sprintf('masked-%s-%s-%d', $table, $column, $index + 1);
                    }
                }

                $transformed[$table][] = $row;
            }
        }

        return $transformed;
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function normalizeOverrides(array $overrides): array
    {
        $normalized = [];

        foreach ($overrides as $column => $value) {
            $normalized[$this->normalizeIdentifier((string) $column)] = $value;
        }

        return $normalized;
    }

    private function isPasswordColumn(string $column): bool
    {
        return in_array($column, ['password', 'password_hash'], true);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $rowsByTable
     */
    private function source(string $class, array $rowsByTable, ?string $password, ?string $connection): string
    {
        $usesPasswordHasher = $password !== null && $this->valueContainsPasswordHash($rowsByTable);
        $usesExplicitConnection = $connection !== null;
        $namespace = Naming::namespace($class, $this->config->namespace()) ?? $this->config->namespace();
        $className = Naming::classBasename($class);
        $tables = array_keys($rowsByTable);
        $truncateTables = array_reverse($tables);
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            sprintf('namespace %s;', $namespace),
            '',
        ];

        if ($usesPasswordHasher) {
            $lines[] = 'use App\\Auth\\PasswordHasher;';
        }

        $lines = array_merge($lines, [
            'use App\\Database\\Seeders\\SeedContext;',
            'use App\\Database\\Seeders\\Seeder;',
        ]);

        if (!$usesExplicitConnection) {
            $lines[] = 'use App\\Database\\Seeders\\ShouldTruncate;';
        }

        $lines = array_merge($lines, [
            '',
            sprintf('final class %s extends Seeder', $className),
            '{',
        ]);

        if (!$usesExplicitConnection) {
            $lines[] = '    use ShouldTruncate;';
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            '    protected function tablesToTruncate(): array',
            '    {',
            '        return ' . $this->exportArray($truncateTables, 2) . ';',
            '    }',
            '',
        ]);

        if ($usesExplicitConnection) {
            $lines = array_merge($lines, [
                '    public function truncate(SeedContext $context): void',
                '    {',
                '        $context->truncateTables(',
                '            $this->tablesToTruncate(),',
                sprintf('            %s,', var_export($connection, true)),
                '        );',
                '    }',
                '',
            ]);
        }

        $lines = array_merge($lines, [
            '    public function run(SeedContext $context): void',
            '    {',
        ]);
        $lines[] = $usesExplicitConnection
            ? sprintf('        $database = $context->database(%s);', var_export($connection, true))
            : '        $database = $context->database();';
        $lines[] = '';

        if ($usesPasswordHasher) {
            $lines[] = sprintf(
                '        $passwordHash = $context->make(PasswordHasher::class)->hash(%s);',
                var_export($password, true),
            );
            $lines[] = '';
        }

        foreach ($rowsByTable as $table => $rows) {
            $method = $this->rowsMethodName($table);
            $methodArguments = $usesPasswordHasher && $this->valueContainsPasswordHash($rows) ? '$passwordHash' : '';
            $lines[] = sprintf("        foreach (\$this->%s(%s) as \$row) {", $method, $methodArguments);
            $lines[] = sprintf("            \$query = \$database->query()->insertInto('%s')->values(\$row);", $table);
            $lines[] = '            $database->insert($query->toSql(), $query->getBindings());';
            $lines[] = '        }';
            $lines[] = '';
        }

        if (end($lines) === '') {
            array_pop($lines);
        }

        $lines[] = '    }';

        foreach ($rowsByTable as $table => $rows) {
            $lines[] = '';
            $lines[] = '    /**';
            $lines[] = '     * @return list<array<string, mixed>>';
            $lines[] = '     */';
            $methodParameters = $usesPasswordHasher && $this->valueContainsPasswordHash($rows)
                ? 'string $passwordHash'
                : '';
            $lines[] = sprintf('    private function %s(%s): array', $this->rowsMethodName($table), $methodParameters);
            $lines[] = '    {';
            $lines[] = '        return ' . $this->exportArray($rows, 2) . ';';
            $lines[] = '    }';
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function rowsMethodName(string $table): string
    {
        return lcfirst(Naming::studly($table)) . 'Rows';
    }

    private function valueContainsPasswordHash(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (array_key_exists('__password_hash__', $value)) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->valueContainsPasswordHash($item)) {
                return true;
            }
        }

        return false;
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

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException(sprintf('Identifier [%s] is not valid.', $identifier));
        }

        return $identifier;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function exportArray(array $values, int $indentLevel): string
    {
        return $this->exportValue($values, $indentLevel);
    }

    private function exportValue(mixed $value, int $indentLevel): string
    {
        if (is_array($value) && array_key_exists('__password_hash__', $value)) {
            return '$passwordHash';
        }

        if (!is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $indentLevel);
        $childIndent = str_repeat('    ', $indentLevel + 1);
        $lines = ['['];

        foreach ($value as $key => $item) {
            $prefix = is_int($key) ? '' : var_export($key, true) . ' => ';
            $lines[] = $childIndent . $prefix . $this->exportValue($item, $indentLevel + 1) . ',';
        }

        $lines[] = $indent . ']';

        return implode("\n", $lines);
    }
}
