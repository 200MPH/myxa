<?php

declare(strict_types=1);

namespace Test\Unit\Migrations;

use App\Config\ConfigRepository;
use App\Database\Migrations\MigrationConfig;
use App\Database\Migrations\MigrationLoader;
use App\Database\Migrations\MigrationManager;
use App\Database\Migrations\MigrationRepository;
use App\Database\Migrations\MigrationScaffolder;
use App\Database\Migrations\ModelScaffolder;
use Myxa\Database\Connection\PdoConnection;
use Myxa\Database\Connection\PdoConnectionConfig;
use Myxa\Database\DatabaseManager;
use Myxa\Database\Schema\Blueprint;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;

#[CoversClass(MigrationConfig::class)]
#[CoversClass(MigrationLoader::class)]
#[CoversClass(MigrationManager::class)]
#[CoversClass(MigrationRepository::class)]
#[CoversClass(MigrationScaffolder::class)]
#[CoversClass(ModelScaffolder::class)]
final class MigrationWorkflowTest extends TestCase
{
    private string $connection;

    private string $rootPath;

    private MigrationConfig $config;

    private DatabaseManager $database;

    private MigrationLoader $loader;

    private MigrationManager $manager;

    private MigrationScaffolder $migrationScaffolder;

    private ModelScaffolder $modelScaffolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = 'migration-test-' . uniqid('', true);
        $this->rootPath = sys_get_temp_dir() . '/myxa-migration-test-' . uniqid('', true);

        mkdir($this->rootPath, 0777, true);
        mkdir($this->rootPath . '/migrations', 0777, true);
        mkdir($this->rootPath . '/schema', 0777, true);
        mkdir($this->rootPath . '/models', 0777, true);

        PdoConnection::register($this->connection, $this->makeInMemoryConnection(), true);

        $repository = new MigrationRepository(
            new DatabaseManager($this->connection),
            $this->makeConfig(),
        );

        $loader = new MigrationLoader($this->makeConfig());
        $this->loader = $loader;
        $this->migrationScaffolder = new MigrationScaffolder($this->makeConfig());
        $this->database = new DatabaseManager($this->connection);
        $this->config = $this->makeConfig();
        $this->manager = new MigrationManager(
            $this->database,
            $this->config,
            $repository,
            $loader,
            $this->migrationScaffolder,
        );
        $this->modelScaffolder = new ModelScaffolder(
            $this->database,
            $this->config,
            $loader,
        );
    }

    protected function tearDown(): void
    {
        PdoConnection::unregister($this->connection);
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testMigrationManagerCanApplyAndRollbackGeneratedMigration(): void
    {
        $path = $this->migrationScaffolder->make('create_posts_table', 'posts');

        self::assertFileExists($path);

        $applied = $this->manager->migrate($this->connection);

        self::assertCount(1, $applied);
        self::assertSame(
            ['migrations', 'posts'],
            $this->database->schema($this->connection)->reverseEngineer()->tables(),
        );

        $rolledBack = $this->manager->rollback(1, $this->connection);

        self::assertCount(1, $rolledBack);
        self::assertSame(['migrations'], $this->database->schema($this->connection)->reverseEngineer()->tables());
    }

    public function testMigrationLoaderDiscoversLoadsAndResolvesMigrationPaths(): void
    {
        $alphaPath = $this->migrationScaffolder->make('create_alpha_table', 'alpha');
        $zetaPath = $this->migrationScaffolder->make('create_zeta_table', 'zeta');

        $paths = $this->loader->discoverPaths();
        $loaded = $this->loader->loadAll();

        self::assertSame([$alphaPath, $zetaPath], $paths);
        self::assertSame(
            [basename($alphaPath, '.php'), basename($zetaPath, '.php')],
            array_map(static fn ($migration): string => $migration->name, $loaded),
        );
        self::assertSame($alphaPath, $this->loader->resolvePath(basename($alphaPath)));
        self::assertSame($zetaPath, $this->loader->resolvePath($zetaPath));
    }

    public function testMigrationLoaderRejectsMissingMigrationFiles(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not be resolved');

        $this->loader->resolvePath('missing_migration.php');
    }

    public function testMigrationLoaderRejectsFilesWithoutConcreteMigrationClasses(): void
    {
        $path = $this->rootPath . '/migrations/' . date('Y_m_d_His') . '_invalid_migration.php';
        file_put_contents($path, <<<'PHP'
<?php

declare(strict_types=1);

final class InvalidMigrationFile
{
}
PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not declare a concrete migration class');

        $this->loader->loadPath($path);
    }

    public function testSnapshotDiffAndReverseEngineeringWorkTogether(): void
    {
        $schema = $this->database->schema($this->connection);

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
        });

        $snapshotPath = $this->manager->snapshot($this->connection);
        self::assertFileExists($snapshotPath);

        $schema->table('users', function (Blueprint $table): void {
            $table->string('nickname')->nullable();
        });

        [$diff, $source] = $this->manager->diff('users', $this->connection);

        self::assertTrue($diff->hasChanges());
        self::assertStringContainsString('AlterUsersTable', $source);

        $reversePath = $this->manager->reverse('users', $this->connection, 'CreateUsersTable');

        self::assertFileExists($reversePath);
        self::assertStringContainsString('CreateUsersTable', (string) file_get_contents($reversePath));
    }

    public function testMigrationManagerStatusShowsRanAndPendingMigrations(): void
    {
        $this->migrationScaffolder->make('create_applied_table', 'applied_items');
        $this->manager->migrate($this->connection);
        $this->migrationScaffolder->make('create_pending_table', 'pending_items');

        $status = $this->manager->status($this->connection);

        self::assertCount(2, $status);
        self::assertSame(basename($this->loader->discoverPaths()[0], '.php'), $status[0]['migration']);
        self::assertSame('ran', $status[0]['status']);
        self::assertSame(1, $status[0]['batch']);
        self::assertSame(basename($this->loader->discoverPaths()[1], '.php'), $status[1]['migration']);
        self::assertSame('pending', $status[1]['status']);
        self::assertNull($status[1]['batch']);
    }

    public function testMigrationManagerCanWriteDiffMigrationFile(): void
    {
        $schema = $this->database->schema($this->connection);

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
        });

        $this->manager->snapshot($this->connection);

        $schema->table('users', function (Blueprint $table): void {
            $table->string('nickname')->nullable();
        });

        $path = $this->manager->writeDiffMigration('users', $this->connection, null, 'AlterUsersTableAddNickname');

        self::assertFileExists($path);
        self::assertStringContainsString('AlterUsersTableAddNickname', (string) file_get_contents($path));
        self::assertStringContainsString('alter_users_table', basename($path));
    }

    public function testMigrationManagerDiffFailsWhenSnapshotIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Run migrate:snapshot first');

        $this->manager->diff('users', $this->connection);
    }

    public function testMigrationManagerRollbackFailsWhenAppliedMigrationFileIsMissing(): void
    {
        $path = $this->migrationScaffolder->make('create_removed_table', 'removed_items');
        $this->manager->migrate($this->connection);
        unlink($path);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not be found');

        $this->manager->rollback(1, $this->connection);
    }

    public function testMigrationScaffolderCanGenerateAlterAndGenericMigrationStubs(): void
    {
        $alterPath = $this->migrationScaffolder->make(
            'alter_users_table',
            createTable: null,
            table: 'users',
            className: 'AlterUsersTable',
            connection: 'analytics',
        );
        $genericPath = $this->migrationScaffolder->make(
            'sync_legacy_data',
            createTable: null,
            table: null,
            className: 'SyncLegacyData',
        );

        $alterSource = (string) file_get_contents($alterPath);
        $genericSource = (string) file_get_contents($genericPath);

        self::assertStringContainsString("return 'analytics';", $alterSource);
        self::assertStringContainsString("\$schema->table('users'", $alterSource);
        self::assertStringContainsString('// TODO: Reverse the table changes.', $alterSource);
        self::assertStringContainsString('final class SyncLegacyData extends Migration', $genericSource);
        self::assertStringContainsString('// TODO: Define the forward migration.', $genericSource);
        self::assertStringContainsString('// TODO: Define the rollback migration.', $genericSource);
    }

    public function testMigrationScaffolderRejectsBlankNames(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not be normalized');

        $this->migrationScaffolder->make('   ');
    }

    public function testModelScaffolderCanGenerateModelFromLiveTable(): void
    {
        $schema = $this->database->schema($this->connection);

        $schema->create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event');
            $table->timestamps();
        });

        $path = $this->modelScaffolder->make(
            'App\\Models\\AuditLog',
            fromTable: 'audit_logs',
            connection: $this->connection,
        );

        $source = (string) file_get_contents($path);

        self::assertFileExists($path);
        self::assertStringContainsString('namespace App\\Models;', $source);
        self::assertStringContainsString("protected string \$table = 'audit_logs';", $source);
        self::assertStringContainsString('use HasTimestamps;', $source);
    }

    public function testModelScaffolderAcceptsSlashDelimitedNames(): void
    {
        $path = $this->modelScaffolder->make('Admin/AuditLog');
        $source = (string) file_get_contents($path);

        self::assertSame($this->rootPath . '/models/Admin/AuditLog.php', $path);
        self::assertStringContainsString('namespace App\\Models\\Admin;', $source);
        self::assertStringContainsString('final class AuditLog extends Model', $source);
        self::assertStringContainsString("protected string \$table = 'audit_logs';", $source);
    }

    public function testModelScaffolderCanGenerateModelFromMigrationFile(): void
    {
        $migrationPath = $this->migrationScaffolder->make('create_comments_table', 'comments');
        $path = $this->modelScaffolder->make(
            'App\\Models\\Comment',
            fromMigration: basename($migrationPath),
            connection: $this->connection,
        );

        $source = (string) file_get_contents($path);

        self::assertFileExists($path);
        self::assertStringContainsString('namespace App\\Models;', $source);
        self::assertStringContainsString('final class Comment extends Model', $source);
        self::assertStringContainsString("protected string \$table = 'comments';", $source);
    }

    public function testModelScaffolderHelpersAndGuardsRejectInvalidInputs(): void
    {
        self::assertSame('Admin\\AuditLog', $this->modelScaffolder->normalizeName('/Admin/AuditLog/'));
        self::assertSame('App\\Models\\Admin', $this->modelScaffolder->normalizeNamespace('Admin\\AuditLog'));

        try {
            $this->modelScaffolder->normalizeName('////');
            self::fail('Expected blank model names to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('could not be resolved', $exception->getMessage());
        }

        try {
            $this->modelScaffolder->normalizeNamespace('App\\Support\\Thing');
            self::fail('Expected invalid model namespace to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('must live under App\\Models', $exception->getMessage());
        }

        try {
            $this->modelScaffolder->make('AuditLog', fromTable: 'audit_logs', fromMigration: 'example.php');
            self::fail('Expected conflicting model sources to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Choose only one model source', $exception->getMessage());
        }
    }

    public function testModelScaffolderUsesExplicitTableNamesAndRejectsDuplicateFiles(): void
    {
        $path = $this->modelScaffolder->make('App\\Models\\Invoice', table: 'billing_invoices');
        $source = (string) file_get_contents($path);

        self::assertStringContainsString("protected string \$table = 'billing_invoices';", $source);

        try {
            $this->modelScaffolder->make('App\\Models\\Invoice', table: 'billing_invoices');
            self::fail('Expected duplicate model file generation to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('already exists', $exception->getMessage());
        }
    }

    private function makeConfig(): MigrationConfig
    {
        return new MigrationConfig(new ConfigRepository([
            'database' => [
                'default' => $this->connection,
            ],
            'migrations' => [
                'repository_table' => 'migrations',
                'paths' => [
                    'migrations' => $this->rootPath . '/migrations',
                    'schema' => $this->rootPath . '/schema',
                ],
                'models' => [
                    'path' => $this->rootPath . '/models',
                    'namespace' => 'App\\Models',
                ],
            ],
        ]));
    }

    private function makeInMemoryConnection(): PdoConnection
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $connection = new PdoConnection(new PdoConnectionConfig(
            engine: 'mysql',
            database: 'placeholder',
            host: '127.0.0.1',
        ));

        $property = new ReflectionProperty(PdoConnection::class, 'pdo');
        $property->setValue($connection, $pdo);

        return $connection;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeDirectory($child);
                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
