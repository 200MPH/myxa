<?php

declare(strict_types=1);

namespace Test\Unit\Auth;

use App\Auth\AuthConfig;
use App\Auth\AuthInstallService;
use App\Auth\BearerTokenResolver;
use App\Auth\PasswordHasher;
use App\Auth\SessionManager;
use App\Auth\SessionUserResolver;
use App\Auth\TokenManager;
use App\Auth\UserManager;
use App\Config\ConfigRepository;
use App\Database\Migrations\MigrationConfig;
use App\Database\Migrations\MigrationLoader;
use App\Database\Migrations\MigrationManager;
use App\Database\Migrations\MigrationRepository;
use App\Database\Migrations\MigrationScaffolder;
use Myxa\Database\Connection\PdoConnection;
use Myxa\Database\Connection\PdoConnectionConfig;
use Myxa\Database\DatabaseManager;
use Myxa\Database\Model\Model;
use Myxa\Http\Request;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;

#[CoversClass(AuthConfig::class)]
#[CoversClass(AuthInstallService::class)]
#[CoversClass(BearerTokenResolver::class)]
#[CoversClass(PasswordHasher::class)]
#[CoversClass(SessionManager::class)]
#[CoversClass(SessionUserResolver::class)]
#[CoversClass(TokenManager::class)]
#[CoversClass(UserManager::class)]
final class AuthServicesTest extends TestCase
{
    private string $connection;

    private string $rootPath;

    private DatabaseManager $database;

    private MigrationManager $migrations;

    private AuthInstallService $install;

    private UserManager $users;

    private TokenManager $tokens;

    private SessionManager $sessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = 'auth-test-' . uniqid('', true);
        $this->rootPath = sys_get_temp_dir() . '/myxa-auth-test-' . uniqid('', true);

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
        $this->database = new DatabaseManager($this->connection);
        Model::setManager($this->database);

        $repository = new MigrationRepository($this->database, $migrationConfig);
        $loader = new MigrationLoader($migrationConfig);
        $scaffolder = new MigrationScaffolder($migrationConfig);

        $this->migrations = new MigrationManager(
            $this->database,
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
        $this->sessions = new SessionManager($authConfig, $this->users);
    }

    protected function tearDown(): void
    {
        PdoConnection::unregister($this->connection);
        $this->removeDirectory($this->rootPath);

        parent::tearDown();
    }

    public function testAuthInstallCreatesMissingMigrationsAndSkipsExistingOnSecondRun(): void
    {
        $first = $this->install->install();
        $second = $this->install->install();

        self::assertCount(3, $first['created']);
        self::assertSame([], $first['skipped']);
        self::assertSame([], $second['created']);
        self::assertSame([
            'create_users_table',
            'create_personal_access_tokens_table',
            'create_user_sessions_table',
        ], $second['skipped']);
    }

    public function testUserTokenAndSessionManagersWorkTogether(): void
    {
        $this->install->install();
        $this->migrations->migrate($this->connection);

        $user = $this->users->create('dev@example.com', 'secret-123', 'Dev User');
        $issuedToken = $this->tokens->issue($user, 'cli', ['users:read', 'users:*']);
        $issuedSession = $this->sessions->issue($user);

        $resolvedToken = $this->tokens->resolve($issuedToken['plain_text_token']);
        $resolvedSession = $this->sessions->resolve($issuedSession['plain_text_session']);
        $reloadedUser = \App\Models\User::query()->with('tokens', 'sessions')->find((int) $user->getKey());

        self::assertNotNull($resolvedToken);
        self::assertTrue($resolvedToken->allowsScope('users:write'));
        self::assertTrue($resolvedToken->allowsScope('users:read'));
        self::assertNotNull($resolvedSession);
        self::assertInstanceOf(\App\Models\User::class, $reloadedUser);
        self::assertCount(1, $reloadedUser->getRelation('tokens'));
        self::assertCount(1, $reloadedUser->getRelation('sessions'));
        self::assertInstanceOf(\App\Models\User::class, $resolvedToken->user()->first());
        self::assertInstanceOf(\App\Models\User::class, $resolvedSession->user()->first());

        $tokenResolver = new BearerTokenResolver($this->tokens, $this->users);
        $sessionResolver = new SessionUserResolver($this->sessions, $this->users);
        $request = new Request(
            cookies: ['auth_session' => $issuedSession['plain_text_session']],
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $issuedToken['plain_text_token']],
        );

        $tokenUser = $tokenResolver->resolve($issuedToken['plain_text_token'], $request);
        $sessionUser = $sessionResolver->resolve($issuedSession['plain_text_session'], $request);

        self::assertInstanceOf(\App\Models\User::class, $tokenUser);
        self::assertInstanceOf(\App\Models\User::class, $sessionUser);
        self::assertTrue($tokenUser->hasTokenScope('users:read'));
        self::assertNotNull($tokenUser->currentAccessToken());
        self::assertNotNull($sessionUser->currentSession());
    }

    public function testTokenRevokeAndPruneRemoveExpiredOrRevokedTokens(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $user = $this->users->create('ops@example.com', 'secret-123', 'Ops');
        $active = $this->tokens->issue($user, 'active', ['*']);
        $expired = $this->tokens->issue($user, 'expired', ['*'], new \DateTimeImmutable('-1 day'));

        self::assertTrue($this->tokens->revoke((int) $active['token']->getKey()));

        $deleted = $this->tokens->prune();

        self::assertSame(2, $deleted);
        self::assertSame([], $this->tokens->list());
        self::assertNull($this->tokens->resolve($expired['plain_text_token']));
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
