<?php

declare(strict_types=1);

namespace Test\Unit\Maintenance;

use App\Maintenance\MaintenanceMode;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(MaintenanceMode::class)]
final class MaintenanceModeTest extends TestCase
{
    private MaintenanceMode $maintenance;

    private string $markerPath;

    private string $activityPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenance = new MaintenanceMode(base_path());
        $this->markerPath = base_path('maintenance.json');
        $this->activityPath = storage_path('maintenance/console-activity.json');

        $this->cleanupState();
    }

    protected function tearDown(): void
    {
        $this->cleanupState();

        parent::tearDown();
    }

    public function testEnableCreatesVisibleMarkerFile(): void
    {
        $this->maintenance->enable('phpunit');

        self::assertTrue($this->maintenance->isEnabled());
        self::assertFileExists($this->markerPath);
        self::assertSame($this->markerPath, $this->maintenance->markerPath());
        self::assertSame('phpunit', $this->maintenance->payload()['activated_by']);
        self::assertIsInt($this->maintenance->payload()['enabled_at_unix']);
        self::assertIsString($this->maintenance->payload()['enabled_at']);
    }

    public function testConsoleActivityTrackingCountsOnlyRegisteredCommands(): void
    {
        $first = $this->maintenance->beginConsoleActivity('route:cache');
        $second = $this->maintenance->beginConsoleActivity('queue:work');

        self::assertSame(2, $this->maintenance->activeConsoleCommandCount());

        $this->maintenance->endConsoleActivity($first);

        self::assertSame(1, $this->maintenance->activeConsoleCommandCount());

        $this->maintenance->endConsoleActivity($second);

        self::assertSame(0, $this->maintenance->activeConsoleCommandCount());
    }

    public function testDisableRemovesMarker(): void
    {
        $this->maintenance->enable('phpunit');

        self::assertTrue($this->maintenance->disable());
        self::assertFalse($this->maintenance->isEnabled());
        self::assertFileDoesNotExist($this->markerPath);
        self::assertTrue($this->maintenance->disable());
    }

    public function testPayloadReturnsEmptyArrayForMissingOrInvalidMarker(): void
    {
        self::assertSame([], $this->maintenance->payload());

        file_put_contents($this->markerPath, '{invalid json');

        self::assertSame([], $this->maintenance->payload());
    }

    public function testEndConsoleActivityIgnoresBlankTokenAndWaitForIdleReturnsTrueWhenIdle(): void
    {
        $this->maintenance->endConsoleActivity(null);
        $this->maintenance->endConsoleActivity('');

        self::assertTrue($this->maintenance->waitForIdleConsole(1, 100));
        self::assertSame([], $this->maintenance->activeConsoleCommands());
    }

    public function testWaitForIdleConsoleTimesOutWhenTrackedCommandRemainsActive(): void
    {
        $this->maintenance->beginConsoleActivity('route:cache');

        self::assertFalse($this->maintenance->waitForIdleConsole(1, 100));
    }

    public function testActiveConsoleCommandsPrunesStaleEntriesFromPersistedState(): void
    {
        $directory = dirname($this->activityPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->activityPath, json_encode([
            'commands' => [
                'stale-command' => [
                    'command' => 'queue:work',
                    'pid' => -1,
                    'started_at' => gmdate(DATE_ATOM),
                    'started_at_unix' => time(),
                ],
                'current-command' => [
                    'command' => 'route:cache',
                    'pid' => getmypid(),
                    'started_at' => gmdate(DATE_ATOM),
                    'started_at_unix' => time(),
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $active = $this->maintenance->activeConsoleCommands();

        self::assertCount(1, $active);
        self::assertArrayHasKey('current-command', $active);
        self::assertSame('route:cache', $active['current-command']['command']);
    }

    public function testActiveConsoleCommandsTreatsInvalidPersistedStateAsEmpty(): void
    {
        $directory = dirname($this->activityPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->activityPath, '{"commands":"invalid"}');

        self::assertSame([], $this->maintenance->activeConsoleCommands());
        self::assertSame(0, $this->maintenance->activeConsoleCommandCount());
    }

    private function cleanupState(): void
    {
        if (is_file($this->markerPath)) {
            @unlink($this->markerPath);
        }

        if (is_file($this->activityPath)) {
            @unlink($this->activityPath);
        }

        $directory = dirname($this->activityPath);
        if (is_dir($directory) && (glob($directory . '/*') ?: []) === []) {
            @rmdir($directory);
        }
    }
}
