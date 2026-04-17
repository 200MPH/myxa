<?php

declare(strict_types=1);

namespace Test\Unit\Events;

use App\Events\EventScaffolder;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(EventScaffolder::class)]
final class EventScaffolderTest extends TestCase
{
    private string $eventsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-event-scaffolder-' . uniqid('', true);
        $this->eventsPath = $rootPath . '/app/Events';

        mkdir($this->eventsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->eventsPath))));

        parent::tearDown();
    }

    public function testMakeCreatesDefaultEvent(): void
    {
        $scaffolder = new EventScaffolder($this->eventsPath);

        $result = $scaffolder->make('UserRegistered');
        $source = file_get_contents($result['path']);

        self::assertSame($this->eventsPath . '/UserRegistered.php', $result['path']);
        self::assertSame('App\\Events\\UserRegistered', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('final readonly class UserRegistered extends AbstractEvent', $source);
    }

    public function testMakeAcceptsSlashDelimitedEventNames(): void
    {
        $scaffolder = new EventScaffolder($this->eventsPath);

        $result = $scaffolder->make('Auth/UserLoggedIn');
        $source = file_get_contents($result['path']);

        self::assertSame($this->eventsPath . '/Auth/UserLoggedIn.php', $result['path']);
        self::assertSame('App\\Events\\Auth\\UserLoggedIn', $result['class']);
        self::assertIsString($source);
        self::assertStringContainsString('namespace App\\Events\\Auth;', $source);
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
