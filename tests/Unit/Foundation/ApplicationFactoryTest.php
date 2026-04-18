<?php

declare(strict_types=1);

namespace Test\Unit\Foundation;

use App\Config\ConfigRepository;
use App\Foundation\ApplicationFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ApplicationFactory::class)]
final class ApplicationFactoryTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/myxa-application-factory-' . uniqid('', true);

        mkdir($this->basePath, 0777, true);
        mkdir($this->basePath . '/config', 0777, true);
        mkdir($this->basePath . '/public', 0777, true);
        mkdir($this->basePath . '/resources', 0777, true);
        mkdir($this->basePath . '/routes', 0777, true);
        mkdir($this->basePath . '/storage', 0777, true);

        file_put_contents($this->basePath . '/.env', "APPLICATION_FACTORY_TEST_ENV=from-env-file\n");
        file_put_contents($this->basePath . '/config/app.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'name' => 'Factory Test',
    'providers' => [
        \Test\Unit\Foundation\ApplicationFactoryTestProvider::class,
    ],
];
PHP);
        file_put_contents($this->basePath . '/config/demo.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'answer' => 42,
];
PHP);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    public function testCreateBuildsConfiguredAndBootedApplication(): void
    {
        $this->unsetEnvironmentValue('APPLICATION_FACTORY_TEST_ENV');

        $app = ApplicationFactory::create($this->basePath);
        $config = $app->make(ConfigRepository::class);

        self::assertTrue($app->isBooted());
        self::assertSame($this->basePath, $app->make('path.base'));
        self::assertSame($this->basePath . '/config', $app->make('path.config'));
        self::assertSame($this->basePath . '/public', $app->make('path.public'));
        self::assertSame($this->basePath . '/resources', $app->make('path.resources'));
        self::assertSame($this->basePath . '/routes', $app->make('path.routes'));
        self::assertSame($this->basePath . '/storage', $app->make('path.storage'));
        self::assertSame('Factory Test', $config->get('app.name'));
        self::assertSame(42, $config->get('demo.answer'));
        self::assertTrue($app->make('factory.registered'));
        self::assertTrue($app->make('factory.booted'));
        self::assertInstanceOf(
            ApplicationFactoryTestProvider::class,
            $app->getProvider(ApplicationFactoryTestProvider::class),
        );
        self::assertSame('from-env-file', getenv('APPLICATION_FACTORY_TEST_ENV') ?: null);
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
