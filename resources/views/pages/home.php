<main>
    <img class="logo" src="<?= $_e($logoPath) ?>" alt="Myxa logo">
    <h1><?= $_e($appName) ?> is running :-)</h1>
    <ul>
        <li>
            <strong>App URL</strong>
            <?= $_e($appUrl) ?>
        </li>
        <li>
            <strong>Environment</strong>
            <?= $_e($appEnv) ?>
        </li>
        <li>
            <strong>Database</strong>
            <?= $_e($databaseLabel) ?>
        </li>
        <li>
            <strong>Redis</strong>
            <?= $_e($redisLabel) ?>
        </li>
        <li>
            <strong>Useful links</strong>
            <a href="<?= $_e($healthPath) ?>">Health endpoint</a>
        </li>
    </ul>
</main>
