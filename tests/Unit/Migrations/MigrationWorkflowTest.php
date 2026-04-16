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
        self::assertSame(['migrations', 'posts'], $this->database->schema($this->connection)->reverseEngineer()->tables());

        $rolledBack = $this->manager->rollback(1, $this->connection);

        self::assertCount(1, $rolledBack);
        self::assertSame(['migrations'], $this->database->schema($this->connection)->reverseEngineer()->tables());
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
