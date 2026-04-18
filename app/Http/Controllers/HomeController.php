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
        $docsPath = '/docs';
        $healthPath = '/health';
        $logoPreviewPath = '/logo-preview.php';
        $versionDetails = $version->details();
        $databaseHost = $this->displayHost(
            (string) ($dbConfig['host'] ?? 'localhost'),
            ['db', 'mysql', 'mariadb'],
        );
        $databasePort = (string) env('DB_FORWARD_PORT', (string) ($dbConfig['port'] ?? '3306'));
        $redisHost = $this->displayHost(
            (string) ($redisConfig['host'] ?? 'localhost'),
            ['redis'],
        );
        $redisPort = (string) env('REDIS_FORWARD_PORT', (string) ($redisConfig['port'] ?? '6379'));

        $databaseLabel = sprintf(
            '%s:%s/%s',
            $databaseHost,
            $databasePort,
            (string) ($dbConfig['database'] ?? 'myxa'),
        );

        $redisLabel = sprintf(
            '%s:%s',
            $redisHost,
            $redisPort,
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
                'docsPath' => $docsPath,
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

    /**
     * @param list<string> $containerHosts
     */
    private function displayHost(string $host, array $containerHosts): string
    {
        $normalized = strtolower(trim($host));

        return in_array($normalized, $containerHosts, true) ? 'localhost' : $host;
    }
}
