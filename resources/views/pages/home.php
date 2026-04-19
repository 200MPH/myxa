<main>
    <section class="hero">
        <div class="hero-brand">
            <div class="logo-card">
                <img class="logo" src="<?= $_e($logoPath) ?>" alt="Myxa logo">
            </div>
        </div>

        <div class="hero-shell">
            <div class="stack hero-copy">
                <h3><?= $_e($appName) ?> is running :-)</h3>
                <p class="lede">
                    A lean Myxa application is now booted through the framework container, service
                    providers, router, and controller pipeline with the full app runtime in charge.
                </p>
            </div>

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
                    <strong>Version: <?= $_e($appVersion) ?></strong>
                    <small>(<?= $_e($versionSource) ?>)</small>
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
                    <a href="<?= $_e($docsPath) ?>">Documentation</a><br>
                    <a href="<?= $_e($healthPath) ?>">Health endpoint</a>
                </li>
            </ul>
        </div>
    </section>
</main>
