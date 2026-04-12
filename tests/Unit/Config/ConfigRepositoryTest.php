<?php

declare(strict_types=1);

namespace Test\Unit\Config;

use App\Config\ConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Test\TestCase;

#[CoversClass(ConfigRepository::class)]
final class ConfigRepositoryTest extends TestCase
{
    public function testRepositoryReturnsNestedValuesAndDefaults(): void
    {
        $config = new ConfigRepository([
            'app' => [
                'name' => 'Myxa',
                'debug' => true,
            ],
        ]);

        self::assertSame('Myxa', $config->get('app.name'));
        self::assertTrue($config->get('app.debug'));
        self::assertSame('fallback', $config->get('app.url', 'fallback'));
        self::assertSame($config->all(), $config->get());
    }

    public function testRepositoryCanSetNestedValues(): void
    {
        $config = new ConfigRepository();

        $config->set('database.connections.mysql.host', 'db');
        $config->set('database.connections.mysql.port', 3306);

        self::assertSame('db', $config->get('database.connections.mysql.host'));
        self::assertSame(3306, $config->get('database.connections.mysql.port'));
    }

    public function testRepositoryHasChecksExistingPaths(): void
    {
        $config = new ConfigRepository([
            'services' => [
                'redis' => [
                    'default' => 'cache',
                ],
            ],
        ]);

        self::assertTrue($config->has('services.redis.default'));
        self::assertFalse($config->has('services.redis.connections.default'));
    }
}
