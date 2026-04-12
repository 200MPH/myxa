<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use App\Config\ConfigRepository;
use App\Foundation\ApplicationFactory;
use App\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(Config::class)]
final class ConfigFacadeTest extends TestCase
{
    public function testConfigFacadeReadsBootstrapConfiguration(): void
    {
        ApplicationFactory::create(base_path());

        self::assertSame(config('app.name'), Config::get('app.name'));
        self::assertSame('local', Config::get('cache.default'));
        self::assertTrue(Config::has('app.providers'));
    }

    public function testConfigFacadeAllowsMutationOfRepositoryValues(): void
    {
        $repository = new ConfigRepository([
            'app' => [
                'name' => 'Before',
            ],
        ]);

        Config::setRepository($repository);
        Config::set('app.name', 'After');

        self::assertSame('After', Config::get('app.name'));
        self::assertSame('After', config('app.name'));
        self::assertSame(['app' => ['name' => 'After']], Config::all());
    }
}
