<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Config\ConfigRepository;
use App\Version\ApplicationVersion;
use Myxa\Http\Request;
use Myxa\Http\Response;
use Myxa\Support\Html\Html;

final class HomeController
{
    public function __invoke(
        Request $request,
        ConfigRepository $config,
        Html $html,
        ApplicationVersion $version,
    ): Response {
        $appName = (string) $config->get('app.name', 'Myxa App');
        $appUrl = (string) $config->get('app.url', $request->fullUrl());
        $appEnv = (string) $config->get('app.env', 'local');
        $databaseDefault = (string) $config->get('database.default', 'mysql');
        $dbConfig = $config->get(sprintf('database.connections.%s', $databaseDefault), []);
        $redisDefault = (string) $config->get('services.redis.default', 'default');
        $redisConfig = $config->get(sprintf('services.redis.connections.%s', $redisDefault), []);

        $logoPath = '/assets/images/myxa-logo.svg';
        $faviconPath = '/assets/images/myxa-mark.svg';
        $healthPath = '/health';
        $logoPreviewPath = '/logo-preview.php';
        $versionDetails = $version->details();

        $databaseLabel = sprintf(
            '%s:%s/%s',
            (string) ($dbConfig['host'] ?? 'localhost'),
            (string) ($dbConfig['port'] ?? '3306'),
            (string) ($dbConfig['database'] ?? 'myxa'),
        );

        $redisLabel = sprintf(
            '%s:%s',
            (string) ($redisConfig['host'] ?? 'localhost'),
            (string) ($redisConfig['port'] ?? '6379'),
        );

        return (new Response())->html($html->renderPage(
            'pages/home',
            [
                'appName' => $appName,
                'appUrl' => $appUrl,
                'appEnv' => $appEnv,
                'appVersion' => $versionDetails['version'],
                'databaseLabel' => $databaseLabel,
                'redisLabel' => $redisLabel,
                'logoPath' => $logoPath,
                'healthPath' => $healthPath,
                'logoPreviewPath' => $logoPreviewPath,
                'versionSource' => $versionDetails['source'],
            ],
            'layouts/app',
            [
                'title' => $appName,
                'faviconPath' => $faviconPath,
            ],
        ));
    }
}
