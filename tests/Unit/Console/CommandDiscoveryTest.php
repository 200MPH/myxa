<?php

declare(strict_types=1);

namespace Test\Unit\Console;

use App\Console\CommandDiscovery;
use Myxa\Console\CommandInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Test\TestCase;

#[CoversClass(CommandDiscovery::class)]
final class CommandDiscoveryTest extends TestCase
{
    private string $commandsPath;

    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();

        $rootPath = sys_get_temp_dir() . '/myxa-command-discovery-' . uniqid('', true);
        $this->commandsPath = $rootPath . '/app/Console/Commands';
        $this->namespace = 'TestGeneratedCommands' . str_replace('.', '', uniqid('', true));

        mkdir($this->commandsPath . '/Admin', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(dirname(dirname($this->commandsPath))));

        parent::tearDown();
    }

    public function testDiscoverReturnsConcreteCommandClassesInPathOrder(): void
    {
        $this->writePhpFile('AlphaCommand.php', sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

use Myxa\Console\Command;

final class AlphaCommand extends Command
{
    public function name(): string
    {
        return 'alpha';
    }

    protected function handle(): int
    {
        return 0;
    }
}
PHP, $this->namespace));

        $this->writePhpFile('Admin/SyncUsersCommand.php', sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s\Admin;

use Myxa\Console\Command;

final class SyncUsersCommand extends Command
{
    public function name(): string
    {
        return 'admin:sync-users';
    }

    protected function handle(): int
    {
        return 0;
    }
}
PHP, $this->namespace));

        $this->writePhpFile('Helper.php', sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

final class Helper
{
}
PHP, $this->namespace));

        $commands = (new CommandDiscovery($this->commandsPath, $this->namespace))->discover();

        self::assertSame([
            $this->namespace . '\\Admin\\SyncUsersCommand',
            $this->namespace . '\\AlphaCommand',
        ], $commands);
    }

    public function testDiscoverSkipsAbstractCommands(): void
    {
        $this->writePhpFile('BaseCommand.php', sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

use Myxa\Console\Command;

abstract class BaseCommand extends Command
{
}
PHP, $this->namespace));

        self::assertSame([], (new CommandDiscovery($this->commandsPath, $this->namespace))->discover());
    }

    public function testDiscoverReturnsEmptyArrayWhenCommandsDirectoryIsMissing(): void
    {
        self::assertSame([], (new CommandDiscovery($this->commandsPath . '/Missing', $this->namespace))->discover());
    }

    public function testDiscoverRejectsMissingCommandClasses(): void
    {
        $this->writePhpFile('GhostCommand.php', sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;
PHP, $this->namespace));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was not found for file');

        (new CommandDiscovery($this->commandsPath, $this->namespace))->discover();
    }

    public function testDiscoverRejectsCommandClassesThatDoNotImplementTheContract(): void
    {
        $this->writePhpFile('BrokenCommand.php', sprintf(<<<'PHP'
<?php

declare(strict_types=1);

namespace %s;

final class BrokenCommand
{
}
PHP, $this->namespace));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(CommandInterface::class);

        (new CommandDiscovery($this->commandsPath, $this->namespace))->discover();
    }

    private function writePhpFile(string $relativePath, string $source): void
    {
        $path = $this->commandsPath . '/' . $relativePath;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $source);
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
