<?php

declare(strict_types=1);

$appEnv = getenv('APP_ENV') ?: 'local';
$appHost = getenv('APP_HOST') ?: 'myxa.localhost';
$appSslPort = getenv('APP_SSL_PORT') ?: '443';
$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'myxa';
$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = getenv('REDIS_PORT') ?: '6379';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function buildHttpsUrl(string $host, string $port): string
{
    if ($port === '443') {
        return sprintf('https://%s', $host);
    }

    return sprintf('https://%s:%s', $host, $port);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Myxa App</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/myxa-mark.svg">
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top, #f3efe4 0%, transparent 40%),
                linear-gradient(135deg, #0f172a 0%, #1f3a5f 45%, #7c5c3b 100%);
            color: #f8fafc;
        }

        main {
            width: min(720px, calc(100% - 2rem));
            padding: 2rem;
            border-radius: 24px;
            background: rgba(15, 23, 42, 0.78);
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(10px);
        }

        h1 {
            margin-top: 0;
            font-size: clamp(2rem, 5vw, 3.5rem);
            line-height: 1.05;
        }

        .logo {
            display: inline-block;
            width: min(100%, 340px);
            margin-bottom: 1rem;
            filter: drop-shadow(0 12px 28px rgba(15, 23, 42, 0.28));
        }

        p {
            color: #dbe4f0;
            font-size: 1.05rem;
        }

        ul {
            padding: 0;
            margin: 1.5rem 0 0;
            list-style: none;
        }

        li {
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            border-radius: 14px;
            background: rgba(148, 163, 184, 0.12);
            border: 1px solid rgba(226, 232, 240, 0.12);
        }

        strong {
            display: block;
            margin-bottom: 0.2rem;
            color: #f8fafc;
        }
    </style>
</head>
<body>
    <main>
        <img class="logo" src="/assets/images/myxa-logo.svg" alt="Myxa logo">
        <p>Docker stack ready</p>
        <h1>Myxa app is running :-)</h1>
        <ul>
            <li>
                <strong>Local URL</strong>
                <?= escape(buildHttpsUrl($appHost, $appSslPort)) ?>
            </li>
            <li>
                <strong>Environment</strong>
                <?= escape($appEnv) ?>
            </li>
            <li>
                <strong>Database</strong>
                <?= escape(sprintf('%s:%s/%s', $dbHost, $dbPort, $dbName)) ?>
            </li>
            <li>
                <strong>Redis</strong>
                <?= escape(sprintf('%s:%s', $redisHost, $redisPort)) ?>
            </li>
            <li>
                <strong>Certificate</strong>
                Self-signed local development certificate
            </li>
        </ul>
    </main>
</body>
</html>
