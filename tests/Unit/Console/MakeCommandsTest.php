<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Config\ConfigRepository;
use App\Console\CommandScaffolder;
use App\Console\Commands\MakeCommandCommand;
use App\Console\Commands\MakeControllerCommand;
use App\Console\Commands\MakeEventCommand;
use App\Console\Commands\MakeListenerCommand;
use App\Console\Commands\MakeMiddlewareCommand;
use App\Console\Commands\MakeMigrationCommand;
use App\Console\Commands\MakeModelCommand;
use App\Console\Commands\MakeResourceCommand;
use App\Data\DataScaffolder;
use App\Database\Migrations\MigrationConfig;
use App\Database\Migrations\MigrationLoader;
use App\Database\Migrations\MigrationScaffolder;
use App\Database\Migrations\ModelScaffolder;
use App\Events\EventScaffolder;
use App\Http\ControllerScaffolder;
use App\Http\MiddlewareScaffolder;
use App\Listeners\ListenerScaffolder;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use Myxa\Database\Connection\PdoConnection;
use Myxa\Database\Connection\PdoConnectionConfig;
use Myxa\Database\DatabaseManager;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MakeCommandCommand::class)]
#[CoversClass(MakeControllerCommand::class)]
#[CoversClass(MakeEventCommand::class)]
#[CoversClass(MakeListenerCommand::class)]
#[CoversClass(MakeMiddlewareCommand::class)]
#[CoversClass(MakeMigrationCommand::class)]
#[CoversClass(MakeModelCommand::class)]
#[CoversClass(MakeResourceCommand::class)]
final class MakeCommandsTest extends TestCase
{
    private string $rootPath;

    private string $connection;

    private MigrationConfig $migrationConfig;

    private DatabaseManager $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootPath = sys_get_temp_dir() . '/myxa-make-commands-' . uniqid('', true);
        $this->connection = 'make-commands-' . uniqid('', true);

        mkdir($this->rootPath, 0777, true);
        mkdir($this->rootPath . '/app/Console/Commands', 0777, true);
        mkdir($this->rootPath . '/app/Http/Controllers', 0777, true);
        mkdir($this->rootPath . '/app/Http/Middleware', 0777, true);
        mkdir($this->rootPath . '/app/Events', 0777, true);
        mkdir($this->rootPath . '/app/Listeners', 0777, true);
        mkdir($this->rootPath . '/app/Providers', 0777, true);
        mkdir($this->rootPath . '/app/Data', 0777, true);
        mkdir($this->rootPath . '/models', 0777, true);
        mkdir($this->rootPath . '/migrations', 0777, true);
        mkdir($this->rootPath . '/schema', 0777, true);

        file_put_contents($this->rootPath . '/app/Providers/EventServiceProvider.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Providers;

use Myxa\Events\EventHandlerInterface;
use Myxa\Events\EventServiceProvider as FrameworkEventServiceProvider;
use Myxa\Support\ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app()->register(new FrameworkEventServiceProvider($this->listeners()));
    }

    /**
     * @return array<class-string, list<EventHandlerInterface|class-string<EventHandlerInterface>>>
     */
    protected function listeners(): array
    {
        return [];
    }
}
PHP);

        PdoConnection::register($this->connection, $this->makeInMemoryConnection(), true);

        $this->migrationConfig = new MigrationConfig(new ConfigRepository([
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

        $this->database = new DatabaseManager($this->connection);
    }

    protected function tearDown(): void
    {
        PdoConnection::unregister($this->connection);
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testMakeCommandCommandExposesMetadataAndScaffoldsCommand(): void
    {
        $command = new MakeCommandCommand(new CommandScaffolder($this->rootPath . '/app/Console/Commands'));

        self::assertSame('make:command', $command->name());
        self::assertSame('Generate a new console command class and register it.', $command->description());
        self::assertSame('name', $command->parameters()[0]->name());
        self::assertSame('command', $command->options()[0]->name());
        self::assertSame('description', $command->options()[1]->name());

        [$exitCode, $output] = $this->runCommand(
            $command,
            'make:command',
            ['name' => 'Admin/SendDigest'],
            ['command' => 'reports:send'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Console command scaffolded successfully.', $output);
        self::assertFileExists($this->rootPath . '/app/Console/Commands/Admin/SendDigestCommand.php');
    }

    public function testMakeControllerCommandExposesMetadataAndScaffoldsInvokableController(): void
    {
        $command = new MakeControllerCommand(new ControllerScaffolder($this->rootPath . '/app/Http/Controllers'));

        self::assertSame('make:controller', $command->name());
        self::assertSame('Generate a new HTTP controller class.', $command->description());
        self::assertSame('name', $command->parameters()[0]->name());
        self::assertSame('invokable', $command->options()[0]->name());
        self::assertSame('resource', $command->options()[1]->name());

        [$exitCode, $output] = $this->runCommand(
            $command,
            'make:controller',
            ['name' => 'HealthCheck'],
            ['invokable' => true],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Controller scaffolded successfully.', $output);
        self::assertFileExists($this->rootPath . '/app/Http/Controllers/HealthCheckController.php');
    }

    public function testMakeEventAndMiddlewareCommandsExposeMetadataAndScaffoldFiles(): void
    {
        $eventCommand = new MakeEventCommand(new EventScaffolder($this->rootPath . '/app/Events'));
        $middlewareCommand = new MakeMiddlewareCommand(
            new MiddlewareScaffolder($this->rootPath . '/app/Http/Middleware'),
        );

        self::assertSame('make:event', $eventCommand->name());
        self::assertSame('Generate a new application event class.', $eventCommand->description());
        self::assertSame('name', $eventCommand->parameters()[0]->name());

        self::assertSame('make:middleware', $middlewareCommand->name());
        self::assertSame('Generate a new HTTP middleware class.', $middlewareCommand->description());
        self::assertSame('name', $middlewareCommand->parameters()[0]->name());

        [$eventExitCode, $eventOutput] = $this->runCommand(
            $eventCommand,
            'make:event',
            ['name' => 'Auth/UserLoggedIn'],
        );
        [$middlewareExitCode, $middlewareOutput] = $this->runCommand(
            $middlewareCommand,
            'make:middleware',
            ['name' => 'Api/EnsureTokenScope'],
        );

        self::assertSame(0, $eventExitCode);
        self::assertStringContainsString('Event scaffolded successfully.', $eventOutput);
        self::assertFileExists($this->rootPath . '/app/Events/Auth/UserLoggedIn.php');

        self::assertSame(0, $middlewareExitCode);
        self::assertStringContainsString('Middleware scaffolded successfully.', $middlewareOutput);
        self::assertFileExists($this->rootPath . '/app/Http/Middleware/Api/EnsureTokenScopeMiddleware.php');
    }

    public function testMakeListenerCommandExposesMetadataAndScaffoldsRegisteredAndUnregisteredListeners(): void
    {
        $command = new MakeListenerCommand(new ListenerScaffolder(
            $this->rootPath . '/app/Listeners',
            $this->rootPath . '/app/Providers/EventServiceProvider.php',
        ));

        self::assertSame('make:listener', $command->name());
        self::assertSame('Generate a new event listener class.', $command->description());
        self::assertSame('name', $command->parameters()[0]->name());
        self::assertSame('event', $command->options()[0]->name());

        [$registeredExitCode, $registeredOutput] = $this->runCommand(
            $command,
            'make:listener',
            ['name' => 'Auth/TrackLogin'],
            ['event' => 'Auth/UserLoggedIn'],
        );
        [$plainExitCode, $plainOutput] = $this->runCommand(
            $command,
            'make:listener',
            ['name' => 'SendWelcomeEmail'],
        );

        self::assertSame(0, $registeredExitCode);
        self::assertStringContainsString('Listener scaffolded successfully.', $registeredOutput);
        self::assertFileExists($this->rootPath . '/app/Listeners/Auth/TrackLoginListener.php');
        self::assertStringContainsString(
            '\\App\\Events\\Auth\\UserLoggedIn::class => [',
            (string) file_get_contents($this->rootPath . '/app/Providers/EventServiceProvider.php'),
        );

        self::assertSame(0, $plainExitCode);
        self::assertStringContainsString('Listener scaffolded successfully.', $plainOutput);
        self::assertFileExists($this->rootPath . '/app/Listeners/SendWelcomeEmailListener.php');
    }

    public function testMakeMigrationCommandExposesMetadataAndScaffoldsCreateMigration(): void
    {
        $command = new MakeMigrationCommand(new MigrationScaffolder($this->migrationConfig));

        self::assertSame('make:migration', $command->name());
        self::assertSame('Create a new forward migration file.', $command->description());
        self::assertSame('name', $command->parameters()[0]->name());
        self::assertCount(4, $command->options());

        [$exitCode, $output] = $this->runCommand(
            $command,
            'make:migration',
            ['name' => 'create_reports_table'],
            ['create' => 'reports'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Migration created at', $output);

        $files = glob($this->rootPath . '/migrations/*create_reports_table.php');
        self::assertIsArray($files);
        self::assertCount(1, $files);
    }

    public function testMakeModelCommandExposesMetadataAndScaffoldsModel(): void
    {
        $command = new MakeModelCommand(new ModelScaffolder(
            $this->database,
            $this->migrationConfig,
            new MigrationLoader($this->migrationConfig),
        ));

        self::assertSame('make:model', $command->name());
        self::assertSame('Generate a model class following the project model conventions.', $command->description());
        self::assertSame('name', $command->parameters()[0]->name());
        self::assertCount(4, $command->options());

        [$exitCode, $output] = $this->runCommand(
            $command,
            'make:model',
            ['name' => 'Admin/AuditLog'],
            ['table' => 'audit_logs'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Model created at', $output);
        self::assertFileExists($this->rootPath . '/models/Admin/AuditLog.php');
    }

    public function testMakeResourceCommandExposesMetadataAndScaffoldsResourceDataClass(): void
    {
        $command = new MakeResourceCommand(new DataScaffolder($this->rootPath . '/app/Data'));

        self::assertSame('make:resource', $command->name());
        self::assertSame('Generate a new DTO-style resource data class.', $command->description());
        self::assertSame('name', $command->parameters()[0]->name());

        [$exitCode, $output] = $this->runCommand(
            $command,
            'make:resource',
            ['name' => 'Auth/LoginData'],
        );

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Resource data class scaffolded successfully.', $output);
        self::assertFileExists($this->rootPath . '/app/Data/Auth/LoginData.php');
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
        return new PdoConnection(new PdoConnectionConfig(
            engine: 'sqlite',
            database: ':memory:',
            host: 'localhost',
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ));
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
