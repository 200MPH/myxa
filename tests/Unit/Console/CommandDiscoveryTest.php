<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\CommandDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(CommandDiscovery::class)]
final class CommandDiscoveryTest extends TestCase
{
    private string $commandsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-command-discovery-' . uniqid('', true);
        $this->commandsPath = $rootPath . '/app/Console/Commands';

        mkdir($this->commandsPath . '/Nested', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->commandsPath))));

        parent::tearDown();
    }

    public function testDiscoverFindsConcreteCommandClassesRecursively(): void
    {
        file_put_contents($this->commandsPath . '/AlphaProbeCommand.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Myxa\Console\Command;

final class AlphaProbeCommand extends Command
{
    public function name(): string
    {
        return 'alpha:probe';
    }

    protected function handle(): int
    {
        return 0;
    }
}
PHP);

        file_put_contents($this->commandsPath . '/Nested/BetaSweepCommand.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands\Nested;

use Myxa\Console\Command;

final class BetaSweepCommand extends Command
{
    public function name(): string
    {
        return 'beta:sweep';
    }

    protected function handle(): int
    {
        return 0;
    }
}
PHP);

        file_put_contents($this->commandsPath . '/Nested/Helper.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands\Nested;

final class Helper
{
}
PHP);

        $discovery = new CommandDiscovery($this->commandsPath);

        self::assertSame([
            'App\\Console\\Commands\\AlphaProbeCommand',
            'App\\Console\\Commands\\Nested\\BetaSweepCommand',
        ], $discovery->discover());
    }

    public function testDiscoverReturnsEmptyArrayWhenCommandsDirectoryIsMissing(): void
    {
        $discovery = new CommandDiscovery($this->commandsPath . '/Missing');

        self::assertSame([], $discovery->discover());
    }

    public function testDiscoverThrowsWhenCommandClassDoesNotExist(): void
    {
        file_put_contents($this->commandsPath . '/GhostCommand.php', <<<'PHP'
<?php

declare(strict_types=1);
PHP);

        $discovery = new CommandDiscovery($this->commandsPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('was not found for file');
        $discovery->discover();
    }

    public function testDiscoverThrowsWhenCommandClassDoesNotImplementInterface(): void
    {
        file_put_contents($this->commandsPath . '/BrokenCommand.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class BrokenCommand
{
}
PHP);

        $discovery = new CommandDiscovery($this->commandsPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must implement');
        $discovery->discover();
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
