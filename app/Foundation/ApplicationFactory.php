<?php

declare(strict_types=1);

namespace App\Foundation;

use App\Config\ConfigRepository;
use Myxa\Application;

final class ApplicationFactory
{
    public static function create(string $basePath): Application
    {
        defined('MYXA_BASE_PATH') || define('MYXA_BASE_PATH', $basePath);

        Environment::load($basePath . '/.env');

        $app = new Application();
        $config = new ConfigRepository(ConfigLoader::load($basePath . '/config'));

        $app->instance(ConfigRepository::class, $config);
        $app->instance('config', $config);
        $app->instance('path.base', $basePath);
        $app->instance('path.config', $basePath . '/config');
        $app->instance('path.public', $basePath . '/public');
        $app->instance('path.resources', $basePath . '/resources');
        $app->instance('path.routes', $basePath . '/routes');
        $app->instance('path.storage', $basePath . '/storage');

        foreach ($config->get('app.providers', []) as $provider) {
            $app->register($provider);
        }

        $app->boot();

        return $app;
    }
}
