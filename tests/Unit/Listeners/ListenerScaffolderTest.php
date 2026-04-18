<?php

declare(strict_types=1);

namespace Test\Unit\Listeners;

use App\Listeners\ListenerScaffolder;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ListenerScaffolder::class)]
final class ListenerScaffolderTest extends TestCase
{
    private string $listenersPath;
    private string $providerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-listener-scaffolder-' . uniqid('', true);
        $this->listenersPath = $rootPath . '/app/Listeners';
        $this->providerPath = $rootPath . '/app/Providers/EventServiceProvider.php';

        mkdir($this->listenersPath, 0777, true);
        mkdir(dirname($this->providerPath), 0777, true);

        file_put_contents($this->providerPath, <<<'PHP'
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
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->listenersPath))));

        parent::tearDown();
    }

    public function testMakeCreatesDefaultListener(): void
    {
        $scaffolder = new ListenerScaffolder($this->listenersPath, $this->providerPath);

        $result = $scaffolder->make('SendWelcomeEmail');
        $source = file_get_contents($result['path']);
        $providerSource = file_get_contents($this->providerPath);

        self::assertSame($this->listenersPath . '/SendWelcomeEmailListener.php', $result['path']);
        self::assertSame('App\\Listeners\\SendWelcomeEmailListener', $result['class']);
        self::assertNull($result['event']);
        self::assertIsString($source);
        self::assertIsString($providerSource);
        self::assertStringContainsString(
            'final class SendWelcomeEmailListener implements EventHandlerInterface',
            $source,
        );
        self::assertStringContainsString('// Handle the event.', $source);
        self::assertStringContainsString('return [];', $providerSource);
    }

    public function testMakeCreatesTypedListenerForEvent(): void
    {
        $scaffolder = new ListenerScaffolder($this->listenersPath, $this->providerPath);

        $result = $scaffolder->make('SendWelcomeEmail', 'UserRegistered');
        $source = file_get_contents($result['path']);
        $providerSource = file_get_contents($this->providerPath);

        self::assertSame('App\\Events\\UserRegistered', $result['event']);
        self::assertIsString($source);
        self::assertIsString($providerSource);
        self::assertStringContainsString('use App\\Events\\UserRegistered;', $source);
        self::assertStringContainsString('/** @param UserRegistered $event */', $source);
        self::assertStringContainsString('if (!$event instanceof UserRegistered) {', $source);
        self::assertStringContainsString('\\App\\Events\\UserRegistered::class => [', $providerSource);
        self::assertStringContainsString('\\App\\Listeners\\SendWelcomeEmailListener::class,', $providerSource);
    }

    public function testMakeAcceptsSlashDelimitedListenerNames(): void
    {
        $scaffolder = new ListenerScaffolder($this->listenersPath, $this->providerPath);

        $result = $scaffolder->make('Auth/TrackLogin', 'Auth/UserLoggedIn');
        $source = file_get_contents($result['path']);
        $providerSource = file_get_contents($this->providerPath);

        self::assertSame($this->listenersPath . '/Auth/TrackLoginListener.php', $result['path']);
        self::assertSame('App\\Listeners\\Auth\\TrackLoginListener', $result['class']);
        self::assertSame('App\\Events\\Auth\\UserLoggedIn', $result['event']);
        self::assertIsString($source);
        self::assertIsString($providerSource);
        self::assertStringContainsString('namespace App\\Listeners\\Auth;', $source);
        self::assertStringContainsString('use App\\Events\\Auth\\UserLoggedIn;', $source);
        self::assertStringContainsString('\\App\\Events\\Auth\\UserLoggedIn::class => [', $providerSource);
        self::assertStringContainsString('\\App\\Listeners\\Auth\\TrackLoginListener::class,', $providerSource);
    }

    public function testMakeAppendsListenerToExistingEventRegistration(): void
    {
        $scaffolder = new ListenerScaffolder($this->listenersPath, $this->providerPath);

        $scaffolder->make('SendWelcomeEmail', 'UserRegistered');
        $scaffolder->make('ProvisionWorkspace', 'UserRegistered');

        $providerSource = (string) file_get_contents($this->providerPath);

        self::assertStringContainsString('\\App\\Listeners\\SendWelcomeEmailListener::class,', $providerSource);
        self::assertStringContainsString('\\App\\Listeners\\ProvisionWorkspaceListener::class,', $providerSource);
    }

    public function testHelperMethodsNormalizeListenerNamesEventsNamespacesAndPaths(): void
    {
        $scaffolder = new ListenerScaffolder($this->listenersPath, $this->providerPath);

        self::assertSame('Auth\\TrackLogin', $scaffolder->normalizeName('/Auth/TrackLogin/'));
        self::assertSame('App\\Listeners\\Auth', $scaffolder->normalizeNamespace('Auth\\TrackLogin'));
        self::assertSame('App\\Events\\UserRegistered', $scaffolder->normalizeEventClass('UserRegistered'));
        self::assertSame('App\\Events\\Auth\\UserLoggedIn', $scaffolder->normalizeEventClass('Auth/UserLoggedIn'));
        self::assertSame(
            'Myxa\\Events\\EventInterface',
            $scaffolder->normalizeEventClass('Myxa\\Events\\EventInterface'),
        );
        self::assertNull($scaffolder->normalizeEventClass('////'));
        self::assertSame(
            $this->listenersPath . '/Auth/TrackLoginListener.php',
            $scaffolder->listenerPath('App\\Listeners\\Auth', 'TrackLoginListener'),
        );
    }

    public function testHelperMethodsRejectInvalidListenerNamesAndNamespaces(): void
    {
        $scaffolder = new ListenerScaffolder($this->listenersPath, $this->providerPath);

        try {
            $scaffolder->normalizeName('////');
            self::fail('Expected blank listener names to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('could not be resolved', $exception->getMessage());
        }

        try {
            $scaffolder->normalizeNamespace('App\\Models\\User');
            self::fail('Expected invalid listener namespace to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('must live under App\\Listeners', $exception->getMessage());
        }

        try {
            $scaffolder->listenerPath('App\\Models', 'UserListener');
            self::fail('Expected invalid listener path namespace to fail.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('must live under App\\Listeners', $exception->getMessage());
        }
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
