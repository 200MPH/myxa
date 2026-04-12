<?php

declare(strict_types=1);

namespace Test;

use Myxa\Database\Model\Model;
use Myxa\Support\Facades\DB;
use Myxa\Support\Facades\Redis;
use Myxa\Support\Facades\Response;
use Myxa\Support\Facades\Route;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    /** @var array<string, string|false> */
    private array $environmentBackup = [];

    /** @var array<string, mixed> */
    private array $serverBackup = [];

    /** @var array<string, mixed> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $this->envBackup = $_ENV;
        $this->environmentBackup = [];
    }

    protected function tearDown(): void
    {
        foreach (array_keys($this->environmentBackup) as $name) {
            $original = $this->environmentBackup[$name];

            if ($original === false) {
                putenv($name);
            } else {
                putenv(sprintf('%s=%s', $name, $original));
            }
        }

        $_SERVER = $this->serverBackup;
        $_ENV = $this->envBackup;

        DB::clearManager();
        Model::clearManager();
        Redis::clearManager();
        Response::clearResponse();
        Route::clearRouter();

        parent::tearDown();
    }

    protected function setEnvironmentValue(string $name, string $value): void
    {
        if (!array_key_exists($name, $this->environmentBackup)) {
            $this->environmentBackup[$name] = getenv($name);
        }

        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    protected function unsetEnvironmentValue(string $name): void
    {
        if (!array_key_exists($name, $this->environmentBackup)) {
            $this->environmentBackup[$name] = getenv($name);
        }

        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
}
