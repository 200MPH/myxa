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
        self::assertSame('phpunit', $this->maintenance->payload()['activated_by']);
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
