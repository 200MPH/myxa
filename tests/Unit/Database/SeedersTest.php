<?php

declare(strict_types=1);

namespace Test\Unit\Database;

use App\Config\ConfigRepository;
use App\Console\Commands\DbSeedCommand;
use App\Console\Commands\MakeSeederCommand;
use App\Database\Seeders\LoadedSeeder;
use App\Database\Seeders\SeedContext;
use App\Database\Seeders\Seeder;
use App\Database\Seeders\SeederConfig;
use App\Database\Seeders\SeederLoader;
use App\Database\Seeders\SeederManager;
use App\Database\Seeders\SeederScaffolder;
use App\Database\Seeders\ShouldTruncate;
use Myxa\Application;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use Myxa\Database\Connection\PdoConnection;
use Myxa\Database\Connection\PdoConnectionConfig;
use Myxa\Database\DatabaseManager;
use Myxa\Database\Schema\Blueprint;
use Myxa\Mongo\Connection\InMemoryMongoCollection;
use Myxa\Mongo\Connection\MongoConnection;
use Myxa\Mongo\MongoManager;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use Myxa\Redis\RedisManager;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;

#[CoversClass(DbSeedCommand::class)]
#[CoversClass(MakeSeederCommand::class)]
#[CoversClass(LoadedSeeder::class)]
#[CoversClass(SeedContext::class)]
#[CoversClass(Seeder::class)]
#[CoversClass(SeederConfig::class)]
#[CoversClass(SeederLoader::class)]
#[CoversClass(SeederManager::class)]
#[CoversClass(SeederScaffolder::class)]
#[CoversClass(ShouldTruncate::class)]
final class SeedersTest extends TestCase
{
    private string $rootPath;

    private string $namespace;

    private string $sqlConnection;

    private string $analyticsConnection;

    private string $redisConnection;

    private string $redisCacheConnection;

    private string $mongoConnection;

    private SeederConfig $config;

    private DatabaseManager $database;

    private RedisManager $redis;

    private MongoManager $mongo;

    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('.', '', uniqid('T', true));
        $this->rootPath = sys_get_temp_dir() . '/myxa-seeders-test-' . $suffix;
        $this->namespace = 'Database\\Seeders\\' . $suffix;
        $this->sqlConnection = 'sql-main-' . $suffix;
        $this->analyticsConnection = 'analytics-' . $suffix;
        $this->redisConnection = 'redis-main-' . $suffix;
        $this->redisCacheConnection = 'cache-' . $suffix;
        $this->mongoConnection = 'mongo-main-' . $suffix;

        mkdir($this->rootPath . '/seeders', 0777, true);

        $this->database = new DatabaseManager($this->sqlConnection);
        $this->database->addConnection($this->sqlConnection, $this->makeInMemoryConnection());
        $this->database->addConnection($this->analyticsConnection, $this->makeInMemoryConnection());

        $this->redis = new RedisManager($this->redisConnection, new RedisConnection(new InMemoryRedisStore()));
        $this->redis->addConnection($this->redisCacheConnection, new RedisConnection(new InMemoryRedisStore()));

        $this->mongo = new MongoManager($this->mongoConnection);
        $this->mongo->addConnection(
            $this->mongoConnection,
            new MongoConnection(['docs' => new InMemoryMongoCollection()]),
        );

        $repository = new ConfigRepository([
            'database' => [
                'default' => $this->sqlConnection,
            ],
            'services' => [
                'redis' => [
                    'default' => $this->redisConnection,
                ],
            ],
            'seeders' => [
                'path' => $this->rootPath . '/seeders',
                'namespace' => $this->namespace,
                'default' => $this->namespace . '\\DatabaseSeeder',
                'connections' => [
                    'database' => $this->sqlConnection,
                    'redis' => $this->redisConnection,
                    'mongo' => $this->mongoConnection,
                ],
            ],
        ]);

        $this->config = new SeederConfig($repository);

        $this->app = new Application();
        $this->app->instance(ConfigRepository::class, $repository);
        $this->app->instance(DatabaseManager::class, $this->database);
        $this->app->instance(RedisManager::class, $this->redis);
        $this->app->instance(MongoManager::class, $this->mongo);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testSeederManagerRunsDefaultSeederAcrossSqlRedisAndMongoConnections(): void
    {
        $this->database->schema($this->sqlConnection)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $this->writeSeeder('DatabaseSeeder', <<<'PHP'
    public function run(SeedContext $context): void
    {
        $context->database()->pdo()->exec("insert into users (name) values ('Ada')");
        $context->redis()->set('seed:status', 'ready');
        $context->mongo()->collection('docs')->insertOne(['type' => 'seed', 'name' => 'Ada']);
    }
PHP);

        $manager = $this->makeManager();
        $seeded = $manager->seed();

        self::assertSame($this->namespace . '\\DatabaseSeeder', $seeded['class']);
        self::assertSame('Ada', $this->database->select('select name from users')[0]['name']);
        self::assertSame('ready', $this->redis->get('seed:status'));
        self::assertSame(
            ['type' => 'seed', 'name' => 'Ada', '_id' => 1],
            $this->mongo->collection('docs')->findOne(['type' => 'seed']),
        );
    }

    public function testSeederCanUseExplicitConnectionOverrides(): void
    {
        $this->database->schema($this->analyticsConnection)->create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        $this->writeSeeder('AnalyticsSeeder', <<<'PHP'
    public function run(SeedContext $context): void
    {
        $context->database()->pdo($context->databaseConnection())->exec("insert into events (name) values ('synced')");
        $context->redis()->set('seed:connection', (string) $context->redisConnection(), $context->redisConnection());
    }
PHP);

        $manager = $this->makeManager();
        $seeded = $manager->seed('Analytics', $this->analyticsConnection, $this->redisCacheConnection);

        self::assertSame($this->namespace . '\\AnalyticsSeeder', $seeded['class']);
        self::assertSame(
            'synced',
            $this->database->select('select name from events', [], $this->analyticsConnection)[0]['name'],
        );
        self::assertSame(
            $this->redisCacheConnection,
            $this->redis->get('seed:connection', $this->redisCacheConnection),
        );
    }

    public function testMakeSeederCommandScaffoldsSeederFiles(): void
    {
        $command = new MakeSeederCommand(new SeederScaffolder($this->config));

        self::assertSame('make:seeder', $command->name());
        self::assertSame('name', $command->parameters()[0]->name());

        [$exitCode, $output] = $this->runCommand($command, 'make:seeder', ['name' => 'Demo/User']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Seeder created at', $output);
        self::assertFileExists($this->rootPath . '/seeders/Demo/UserSeeder.php');
        self::assertStringContainsString(
            'final class UserSeeder extends Seeder',
            (string) file_get_contents($this->rootPath . '/seeders/Demo/UserSeeder.php'),
        );
    }

    public function testSeederManagerOnlyResolvesStoreManagersWhenSeederUsesThem(): void
    {
        $this->database->schema($this->sqlConnection)->create('minimal_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('message');
        });

        $this->writeSeeder('SqlOnlySeeder', <<<'PHP'
    public function run(SeedContext $context): void
    {
        $context->database()->insert("insert into minimal_logs (message) values ('sql only')");
    }
PHP);

        $app = new Application();
        $app->instance(DatabaseManager::class, $this->database);

        $manager = new SeederManager(
            $app,
            $this->config,
            new SeederLoader($this->config),
        );

        $manager->seed('SqlOnly', $this->sqlConnection);

        self::assertSame(
            'sql only',
            $this->database->select('select message from minimal_logs')[0]['message'],
        );
    }

    public function testDbSeedCommandRunsSelectedSeeder(): void
    {
        $this->database->schema($this->sqlConnection)->create('logs', function (Blueprint $table): void {
            $table->id();
            $table->string('message');
        });

        $this->writeSeeder(
            'LogSeeder',
            <<<'PHP'
    use ShouldTruncate;

    protected function tablesToTruncate(): array
    {
        return ['logs'];
    }

    public function run(SeedContext $context): void
    {
        $context->database()->pdo()->exec("insert into logs (message) values ('command')");
    }
PHP,
        );

        $command = new DbSeedCommand($this->makeManager());

        self::assertSame('db:seed', $command->name());
        self::assertSame('seeder', $command->parameters()[0]->name());
        self::assertSame('connection', $command->options()[0]->name());
        self::assertSame('truncate', $command->options()[3]->name());

        $this->database->insert("insert into logs (message) values ('old')");

        [$exitCode, $output] = $this->runCommand(
            $command,
            'db:seed',
            ['seeder' => 'Log'],
            ['connection' => $this->sqlConnection, 'truncate' => true],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Seeded ' . $this->namespace . '\\LogSeeder.', $output);
        self::assertSame([['message' => 'command']], $this->database->select('select message from logs'));
    }

    private function makeManager(): SeederManager
    {
        return new SeederManager(
            $this->app,
            $this->config,
            new SeederLoader($this->config),
        );
    }

    private function writeSeeder(string $className, string $body): void
    {
        file_put_contents($this->rootPath . '/seeders/' . $className . '.php', implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace ' . $this->namespace . ';',
            '',
            'use App\\Database\\Seeders\\SeedContext;',
            'use App\\Database\\Seeders\\Seeder;',
            'use App\\Database\\Seeders\\ShouldTruncate;',
            '',
            'final class ' . $className . ' extends Seeder',
            '{',
            rtrim($body),
            '}',
            '',
        ]));
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     * @return array{0: int, 1: string}
     */
    private function runCommand(object $command, string $name, array $parameters = [], array $options = []): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);

        $exitCode = $command->run(
            new ConsoleInput($name, $parameters, $options),
            new ConsoleOutput($stream, ansi: false),
        );

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, is_string($output) ? $output : ''];
    }

    private function makeInMemoryConnection(): PdoConnection
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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
