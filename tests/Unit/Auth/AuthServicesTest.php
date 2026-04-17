<?php

declare(strict_types=1);

namespace Test\Unit\Auth;

use App\Auth\AuthConfig;
use App\Auth\AuthInstallService;
use App\Auth\BearerTokenResolver;
use App\Auth\PasswordHasher;
use App\Auth\SessionRecordInterface;
use App\Auth\SessionManager;
use App\Auth\SessionUserResolver;
use App\Auth\TokenManager;
use App\Auth\UserManager;
use App\Auth\Stores\FileSessionStore;
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
use Myxa\Redis\RedisManager;
use Myxa\Redis\Connection\InMemoryRedisStore;
use Myxa\Redis\Connection\RedisConnection;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Test\TestCase;
use DateTimeImmutable;

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
        $this->sessions = new SessionManager($authConfig, $this->users, new \App\Auth\Stores\DatabaseSessionStore());
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

    public function testAuthConfigExposesSessionAndTokenSettings(): void
    {
        $config = new AuthConfig(new ConfigRepository([
            'auth' => [
                'session' => [
                    'driver' => 'redis',
                    'cookie' => 'custom_session',
                    'lifetime' => 900,
                    'http_only' => false,
                    'same_site' => 'Strict',
                    'secure' => true,
                    'length' => 96,
                    'path' => '/tmp/myxa-sessions',
                    'redis' => [
                        'connection' => 'sessions',
                        'prefix' => 'myxa-session:',
                    ],
                ],
                'tokens' => [
                    'length' => 80,
                    'default_name' => 'worker',
                    'default_scopes' => ['users:read', 'users:write'],
                ],
            ],
        ]));

        self::assertSame('redis', $config->sessionDriver());
        self::assertSame('custom_session', $config->sessionCookieName());
        self::assertSame(900, $config->sessionLifetime());
        self::assertFalse($config->sessionHttpOnly());
        self::assertTrue($config->sessionSecure());
        self::assertSame('Strict', $config->sessionSameSite());
        self::assertSame(96, $config->sessionLength());
        self::assertSame('/tmp/myxa-sessions', $config->sessionPath());
        self::assertSame('sessions', $config->sessionRedisConnection());
        self::assertSame('myxa-session:', $config->sessionRedisPrefix());
        self::assertSame(80, $config->tokenLength());
        self::assertSame('worker', $config->defaultTokenName());
        self::assertSame(['users:read', 'users:write'], $config->defaultTokenScopes());
    }

    public function testAuthConfigFallsBackForBlankOrMissingValues(): void
    {
        $config = new AuthConfig(new ConfigRepository([
            'auth' => [
                'session' => [
                    'driver' => '',
                    'same_site' => '   ',
                    'length' => 1,
                ],
                'tokens' => [
                    'default_name' => '   ',
                    'default_scopes' => [' ', ''],
                    'length' => 1,
                ],
            ],
        ]));

        self::assertSame('file', $config->sessionDriver());
        self::assertNull($config->sessionSameSite());
        self::assertSame(32, $config->sessionLength());
        self::assertSame(32, $config->tokenLength());
        self::assertSame('cli', $config->defaultTokenName());
        self::assertSame(['*'], $config->defaultTokenScopes());
    }

    public function testPasswordHasherHashesVerifiesAndRejectsEmptyPasswords(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret-123');

        self::assertNotSame('secret-123', $hash);
        self::assertTrue($hasher->verify('secret-123', $hash));
        self::assertFalse($hasher->verify('wrong', $hash));
        self::assertFalse($hasher->verify('secret-123', ''));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Password cannot be empty.');
        $hasher->hash('   ');
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
        self::assertInstanceOf(\App\Models\UserSession::class, $resolvedSession);
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

    public function testTokenManagerUsesDefaultNameAndScopesWhenNotProvided(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $user = $this->users->create('defaults@example.com', 'secret-123', 'Defaults');
        $issued = $this->tokens->issue($user);

        self::assertSame('cli', $issued['token']->getAttribute('name'));
        self::assertSame('["*"]', $issued['token']->getAttribute('scopes'));
    }

    public function testTokenManagerListsFiltersAndHandlesBlankOrMissingRevocations(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $first = $this->users->create('tokens-first@example.com', 'secret-123', 'First');
        $second = $this->users->create('tokens-second@example.com', 'secret-123', 'Second');
        $firstToken = $this->tokens->issue($first, 'first');
        $secondToken = $this->tokens->issue($second, 'second');

        self::assertNull($this->tokens->resolve('   '));
        self::assertCount(2, $this->tokens->list());
        self::assertCount(1, $this->tokens->list($first));
        self::assertSame($firstToken['token']->getKey(), $this->tokens->list($first)[0]->getKey());
        self::assertCount(1, $this->tokens->list($second));
        self::assertSame($secondToken['token']->getKey(), $this->tokens->list($second)[0]->getKey());
        self::assertFalse($this->tokens->revoke(999999));
        self::assertSame(0, $this->tokens->prune());

        self::assertTrue($this->tokens->revoke((int) $firstToken['token']->getKey()));
        self::assertTrue($this->tokens->revoke((int) $firstToken['token']->getKey()));
    }

    public function testUserManagerCanFindListChangePasswordsAndHandleErrors(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $created = $this->users->create('first@example.com', 'secret-123', 'First User');
        $second = $this->users->create('second@example.com', 'secret-456', null);

        self::assertInstanceOf(\App\Models\User::class, $this->users->find((int) $created->getKey()));
        self::assertInstanceOf(\App\Models\User::class, $this->users->find('FIRST@example.com'));
        self::assertNull($this->users->find('missing@example.com'));
        self::assertCount(2, $this->users->list());
        self::assertCount(1, $this->users->list(1));
        self::assertTrue($this->users->verifyPassword($created, 'secret-123'));

        $updated = $this->users->changePassword($created, 'updated-secret');
        self::assertSame($created->getKey(), $updated->getKey());
        self::assertTrue($this->users->verifyPassword($updated, 'updated-secret'));
        self::assertSame($second->getKey(), $this->users->resolveUser((int) $second->getKey())->getKey());

        try {
            $this->users->create('first@example.com', 'secret-123', 'Duplicate');
            self::fail('Expected duplicate user creation to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('already exists', $exception->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('was not found');
        $this->users->resolveUser('missing@example.com');
    }

    public function testSessionManagerReturnsNullForBlankValuesAndSupportsRevokeBranches(): void
    {
        $this->install->install();
        $this->migrations->migrate($this->connection);

        $user = $this->users->create('session@example.com', 'secret-123', 'Session User');
        $issued = $this->sessions->issue($user);

        self::assertNull($this->sessions->resolve('   '));
        self::assertTrue($this->sessions->revoke((int) $issued['session']->identifier()));
        self::assertTrue($this->sessions->revoke((int) $issued['session']->identifier()));
        self::assertFalse($this->sessions->revoke(999999));
    }

    public function testBearerTokenAndSessionResolversReturnNullWhenBackingUserIsMissing(): void
    {
        $this->install->install();
        $this->migrations->migrate($this->connection);

        $user = $this->users->create('ghost@example.com', 'secret-123', 'Ghost');
        $issuedToken = $this->tokens->issue($user);
        $issuedSession = $this->sessions->issue($user);

        $pdo = $this->database->pdo($this->connection);
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
        self::assertInstanceOf(\PDOStatement::class, $statement);
        self::assertTrue($statement->execute([
            'id' => $user->getKey(),
        ]));
        $pdo->exec('PRAGMA foreign_keys = ON');

        $tokenResolver = new BearerTokenResolver($this->tokens, $this->users);
        $sessionResolver = new SessionUserResolver($this->sessions, $this->users);
        $request = new Request(
            cookies: ['auth_session' => $issuedSession['plain_text_session']],
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $issuedToken['plain_text_token']],
        );

        $pdo->exec('PRAGMA foreign_keys = OFF');

        try {
            self::assertNull($tokenResolver->resolve($issuedToken['plain_text_token'], $request));
            self::assertNull($sessionResolver->resolve($issuedSession['plain_text_session'], $request));
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    public function testBearerTokenResolverReturnsNullForUnknownBearerTokens(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $tokenResolver = new BearerTokenResolver($this->tokens, $this->users);

        self::assertNull($tokenResolver->resolve('missing-token', new Request()));
    }

    public function testSessionManagerSupportsFileDriver(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $sessionsPath = $this->rootPath . '/sessions-file';
        mkdir($sessionsPath, 0777, true);

        $sessionManager = new SessionManager(
            $this->makeAuthConfig([
                'driver' => 'file',
                'path' => $sessionsPath,
            ]),
            $this->users,
            new \App\Auth\Stores\FileSessionStore($sessionsPath),
        );

        $user = $this->users->create('file@example.com', 'secret-123', 'File User');
        $issued = $sessionManager->issue($user);
        $resolved = $sessionManager->resolve($issued['plain_text_session']);

        self::assertInstanceOf(SessionRecordInterface::class, $issued['session']);
        self::assertSame('file', $issued['session']->driver());
        self::assertInstanceOf(SessionRecordInterface::class, $resolved);
        self::assertSame((int) $user->getKey(), $resolved->userId());

        $resolver = new SessionUserResolver($sessionManager, $this->users);
        $request = new Request(cookies: ['auth_session' => $issued['plain_text_session']]);
        $resolvedUser = $resolver->resolve($issued['plain_text_session'], $request);

        self::assertInstanceOf(\App\Models\User::class, $resolvedUser);
        self::assertNotNull($resolvedUser->currentSession());
        self::assertSame('file', $resolvedUser->currentSession()->driver());
    }

    public function testSessionManagerSupportsRedisDriver(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $redis = new RedisManager('sessions', new RedisConnection(new InMemoryRedisStore()));
        $sessionManager = new SessionManager(
            $this->makeAuthConfig([
                'driver' => 'redis',
                'redis' => [
                    'connection' => 'sessions',
                    'prefix' => 'test-session:',
                ],
            ]),
            $this->users,
            new \App\Auth\Stores\RedisSessionStore($redis, 'sessions', 'test-session:'),
        );

        $user = $this->users->create('redis@example.com', 'secret-123', 'Redis User');
        $issued = $sessionManager->issue($user);
        $resolved = $sessionManager->resolve($issued['plain_text_session']);

        self::assertInstanceOf(SessionRecordInterface::class, $resolved);
        self::assertSame('redis', $resolved->driver());
        self::assertSame((int) $user->getKey(), $resolved->userId());

        $resolver = new SessionUserResolver($sessionManager, $this->users);
        $request = new Request(cookies: ['auth_session' => $issued['plain_text_session']]);
        $resolvedUser = $resolver->resolve($issued['plain_text_session'], $request);

        self::assertInstanceOf(\App\Models\User::class, $resolvedUser);
        self::assertNotNull($resolvedUser->currentSession());
        self::assertSame('redis', $resolvedUser->currentSession()->driver());
    }

    public function testSessionUserResolverReturnsNullWhenSessionUserCannotBeFound(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $sessionsPath = $this->rootPath . '/sessions-missing-user';
        mkdir($sessionsPath, 0777, true);

        $store = new FileSessionStore($sessionsPath);
        $store->issue(
            999999,
            'missing-user-session',
            new DateTimeImmutable('+1 hour'),
            new DateTimeImmutable('now'),
        );

        $sessionManager = new SessionManager(
            $this->makeAuthConfig([
                'driver' => 'file',
                'path' => $sessionsPath,
            ]),
            $this->users,
            $store,
        );

        $resolver = new SessionUserResolver($sessionManager, $this->users);

        self::assertNull($resolver->resolve('missing-user-session', new Request()));
    }

    public function testSessionUserResolverReturnsNullWhenSessionCannotBeResolved(): void
    {
        $this->install->install(false);
        $this->migrations->migrate($this->connection);

        $sessionsPath = $this->rootPath . '/sessions-missing-session';
        mkdir($sessionsPath, 0777, true);

        $sessionManager = new SessionManager(
            $this->makeAuthConfig([
                'driver' => 'file',
                'path' => $sessionsPath,
            ]),
            $this->users,
            new FileSessionStore($sessionsPath),
        );

        $resolver = new SessionUserResolver($sessionManager, $this->users);

        self::assertNull($resolver->resolve('missing-session', new Request()));
        self::assertNull($resolver->resolve('   ', new Request()));
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

    private function makeAuthConfig(array $sessionOverrides = []): AuthConfig
    {
        $session = array_replace_recursive([
            'driver' => 'file',
            'cookie' => 'auth_session',
            'lifetime' => 3600,
            'length' => 64,
            'path' => $this->rootPath . '/sessions',
            'redis' => [
                'connection' => 'default',
                'prefix' => 'session:',
            ],
        ], $sessionOverrides);

        return new AuthConfig(new ConfigRepository([
            'auth' => [
                'session' => $session,
                'tokens' => [
                    'length' => 40,
                    'default_name' => 'cli',
                    'default_scopes' => ['*'],
                ],
            ],
        ]));
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
