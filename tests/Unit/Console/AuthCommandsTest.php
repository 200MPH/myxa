<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Auth\AuthConfig;
use App\Auth\AuthInstallService;
use App\Auth\PasswordHasher;
use App\Auth\SessionManager;
use App\Auth\Stores\DatabaseSessionStore;
use App\Auth\TokenManager;
use App\Auth\UserManager;
use App\Config\ConfigRepository;
use App\Console\Commands\AuthInstallCommand;
use App\Console\Commands\TokenCreateCommand;
use App\Console\Commands\TokenListCommand;
use App\Console\Commands\TokenPruneCommand;
use App\Console\Commands\TokenRevokeCommand;
use App\Console\Commands\UserCreateCommand;
use App\Console\Commands\UserListCommand;
use App\Console\Commands\UserPasswordCommand;
use App\Database\Migrations\MigrationConfig;
use App\Database\Migrations\MigrationLoader;
use App\Database\Migrations\MigrationManager;
use App\Database\Migrations\MigrationRepository;
use App\Database\Migrations\MigrationScaffolder;
use Myxa\Console\ConsoleInput;
use Myxa\Console\ConsoleOutput;
use Myxa\Database\Connection\PdoConnection;
use Myxa\Database\Connection\PdoConnectionConfig;
use Myxa\Database\DatabaseManager;
use Myxa\Database\Model\Model;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;

#[CoversClass(AuthInstallCommand::class)]
#[CoversClass(TokenCreateCommand::class)]
#[CoversClass(TokenListCommand::class)]
#[CoversClass(TokenPruneCommand::class)]
#[CoversClass(TokenRevokeCommand::class)]
#[CoversClass(UserCreateCommand::class)]
#[CoversClass(UserListCommand::class)]
#[CoversClass(UserPasswordCommand::class)]
final class AuthCommandsTest extends TestCase
{
    private string $connection;

    private string $rootPath;

    private MigrationManager $migrations;

    private AuthInstallService $install;

    private UserManager $users;

    private TokenManager $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = 'auth-commands-' . uniqid('', true);
        $this->rootPath = sys_get_temp_dir() . '/myxa-auth-commands-' . uniqid('', true);

        mkdir($this->rootPath, 0777, true);
        mkdir($this->rootPath . '/migrations', 0777, true);
        mkdir($this->rootPath . '/schema', 0777, true);

        PdoConnection::register($this->connection, $this->makeInMemoryConnection(), true);

        $config = new ConfigRepository([
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
            'auth' => [
                'session' => [
                    'driver' => 'database',
                    'cookie' => 'auth_session',
                    'lifetime' => 3600,
                    'length' => 64,
                ],
                'tokens' => [
                    'length' => 40,
                    'default_name' => 'cli',
                    'default_scopes' => ['*'],
                ],
            ],
        ]);

        $migrationConfig = new MigrationConfig($config);
        $database = new DatabaseManager($this->connection);
        Model::setManager($database);

        $repository = new MigrationRepository($database, $migrationConfig);
        $loader = new MigrationLoader($migrationConfig);
        $scaffolder = new MigrationScaffolder($migrationConfig);

        $this->migrations = new MigrationManager(
            $database,
            $migrationConfig,
            $repository,
            $loader,
            $scaffolder,
        );
        $this->install = new AuthInstallService($migrationConfig, $scaffolder);

        $authConfig = new AuthConfig($config);
        $passwords = new PasswordHasher();
        $this->users = new UserManager($passwords);
        $this->tokens = new TokenManager($authConfig, $this->users);
        new SessionManager($authConfig, $this->users, new DatabaseSessionStore());
    }

    protected function tearDown(): void
    {
        PdoConnection::unregister($this->connection);
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testAuthInstallCommandCreatesAndSkipsMigrations(): void
    {
        $command = new AuthInstallCommand($this->install);

        [$exitCode, $output] = $this->runCommand($command, 'auth:install');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Auth install complete.', $output);
        self::assertStringContainsString('create_users_table', $output);
        self::assertStringContainsString('create_user_sessions_table', $output);

        [$secondExitCode, $secondOutput] = $this->runCommand(
            $command,
            'auth:install',
            [],
            ['without-sessions' => true],
        );

        self::assertSame(0, $secondExitCode);
        self::assertStringContainsString('Skipped Existing', $secondOutput);
        self::assertStringNotContainsString('create_user_sessions_table', $secondOutput);
    }

    public function testAuthAndUserTokenCommandsExposeMetadata(): void
    {
        $authInstall = new AuthInstallCommand($this->install);
        $tokenCreate = new TokenCreateCommand($this->tokens);
        $tokenPrune = new TokenPruneCommand($this->tokens);
        $tokenRevoke = new TokenRevokeCommand($this->tokens);
        $userCreate = new UserCreateCommand($this->users);
        $userPassword = new UserPasswordCommand($this->users);

        self::assertSame('auth:install', $authInstall->name());
        self::assertSame(
            'Create missing auth migration files for users, sessions, and bearer tokens.',
            $authInstall->description(),
        );
        self::assertSame('without-sessions', $authInstall->options()[0]->name());

        self::assertSame('token:create', $tokenCreate->name());
        self::assertSame('Create a personal access token for a user.', $tokenCreate->description());
        self::assertSame('user', $tokenCreate->parameters()[0]->name());
        self::assertCount(3, $tokenCreate->options());

        self::assertSame('token:prune', $tokenPrune->name());
        self::assertSame('Delete expired and revoked personal access tokens.', $tokenPrune->description());

        self::assertSame('token:revoke', $tokenRevoke->name());
        self::assertSame('Revoke a personal access token by ID.', $tokenRevoke->description());
        self::assertSame('token', $tokenRevoke->parameters()[0]->name());

        self::assertSame('user:create', $userCreate->name());
        self::assertSame('Create a new application user.', $userCreate->description());
        self::assertSame('email', $userCreate->parameters()[0]->name());
        self::assertCount(2, $userCreate->options());

        self::assertSame('user:password', $userPassword->name());
        self::assertSame('Change the password for an existing user.', $userPassword->description());
        self::assertSame('user', $userPassword->parameters()[0]->name());
        self::assertSame('password', $userPassword->options()[0]->name());
    }

    public function testUserCreateListAndPasswordCommandsCoverMainFlows(): void
    {
        $this->install->install();
        $this->migrations->migrate($this->connection);

        $userCreate = new UserCreateCommand($this->users);
        [$createExitCode, $createOutput] = $this->runCommand(
            $userCreate,
            'user:create',
            ['email' => 'console@example.com'],
            ['name' => 'Console User', 'password' => 'secret-123'],
        );

        self::assertSame(0, $createExitCode);
        self::assertStringContainsString('User created successfully.', $createOutput);
        self::assertStringContainsString('console@example.com', $createOutput);
        self::assertStringNotContainsString('Generated password', $createOutput);

        [$generatedExitCode, $generatedOutput] = $this->runCommand(
            $userCreate,
            'user:create',
            ['email' => 'generated@example.com'],
        );

        self::assertSame(0, $generatedExitCode);
        self::assertStringContainsString('Generated password:', $generatedOutput);

        $userList = new UserListCommand($this->users);
        [$listExitCode, $listOutput] = $this->runCommand(
            $userList,
            'user:list',
            [],
            ['limit' => '1'],
        );

        self::assertSame(0, $listExitCode);
        self::assertStringContainsString('console@example.com', $listOutput);

        $userPassword = new UserPasswordCommand($this->users);
        [$passwordExitCode, $passwordOutput] = $this->runCommand(
            $userPassword,
            'user:password',
            ['user' => 'console@example.com'],
            ['password' => 'updated-secret'],
        );

        self::assertSame(0, $passwordExitCode);
        self::assertStringContainsString('Password updated for user [console@example.com].', $passwordOutput);
        self::assertStringNotContainsString('Generated password:', $passwordOutput);

        [$generatedPasswordExitCode, $generatedPasswordOutput] = $this->runCommand(
            $userPassword,
            'user:password',
            ['user' => 'generated@example.com'],
        );

        self::assertSame(0, $generatedPasswordExitCode);
        self::assertStringContainsString('Generated password:', $generatedPasswordOutput);
    }

    public function testUserListCommandHandlesEmptyStateAndMetadata(): void
    {
        $this->install->install();
        $this->migrations->migrate($this->connection);

        $userList = new UserListCommand($this->users);

        [$exitCode, $output] = $this->runCommand($userList, 'user:list');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No users found.', $output);
        self::assertSame('user:list', $userList->name());
        self::assertSame('List application users.', $userList->description());
        self::assertSame('limit', $userList->options()[0]->name());
    }

    public function testTokenCommandsCoverCreateListRevokePruneAndErrors(): void
    {
        $this->install->install();
        $this->migrations->migrate($this->connection);

        $user = $this->users->create('token-owner@example.com', 'secret-123', 'Token Owner');

        $tokenCreate = new TokenCreateCommand($this->tokens);
        [$createExitCode, $createOutput] = $this->runCommand(
            $tokenCreate,
            'token:create',
            ['user' => 'token-owner@example.com'],
            ['name' => 'cli', 'scopes' => 'users:read, users:*', 'expires' => '+1 day'],
        );

        self::assertSame(0, $createExitCode);
        self::assertStringContainsString('Plain token:', $createOutput);
        self::assertStringContainsString('users:read, users:*', $createOutput);

        try {
            $this->runCommand(
                $tokenCreate,
                'token:create',
                ['user' => 'token-owner@example.com'],
                ['expires' => 'definitely-not-a-date'],
            );
            self::fail('Expected invalid expiration to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Invalid token expiration', $exception->getMessage());
        }

        $tokenList = new TokenListCommand($this->tokens, $this->users);
        [$listExitCode, $listOutput] = $this->runCommand(
            $tokenList,
            'token:list',
            ['user' => 'token-owner@example.com'],
        );

        self::assertSame(0, $listExitCode);
        self::assertStringContainsString((string) $user->getKey(), $listOutput);
        self::assertStringContainsString('cli', $listOutput);

        $tokenId = (int) $this->tokens->list($user)[0]->getKey();

        $tokenRevoke = new TokenRevokeCommand($this->tokens);
        [$revokeExitCode, $revokeOutput] = $this->runCommand(
            $tokenRevoke,
            'token:revoke',
            ['token' => (string) $tokenId],
        );

        self::assertSame(0, $revokeExitCode);
        self::assertStringContainsString(sprintf('Token [%d] revoked.', $tokenId), $revokeOutput);

        [$missingRevokeExitCode, $missingRevokeOutput] = $this->runCommand(
            $tokenRevoke,
            'token:revoke',
            ['token' => '999999'],
        );

        self::assertSame(1, $missingRevokeExitCode);
        self::assertStringContainsString('Token [999999] was not found.', $missingRevokeOutput);

        $expired = $this->tokens->issue($user, 'expired', ['*'], new \DateTimeImmutable('-1 day'));
        self::assertIsArray($expired);

        $tokenPrune = new TokenPruneCommand($this->tokens);
        [$pruneExitCode, $pruneOutput] = $this->runCommand($tokenPrune, 'token:prune');

        self::assertSame(0, $pruneExitCode);
        self::assertStringContainsString('Deleted 2 token(s).', $pruneOutput);
    }

    public function testTokenListCommandHandlesEmptyStateAndMetadata(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $tokenList = new TokenListCommand($this->tokens, $this->users);
        [$exitCode, $output] = $this->runCommand($tokenList, 'token:list');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No tokens found.', $output);
        self::assertSame('token:list', $tokenList->name());
        self::assertSame('List personal access tokens.', $tokenList->description());
        self::assertSame('user', $tokenList->parameters()[0]->name());
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
        $output = (string) stream_get_contents($stream);
        fclose($stream);

        return [$exitCode, $output];
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

            @unlink($child);
        }

        @rmdir($path);
    }
}
