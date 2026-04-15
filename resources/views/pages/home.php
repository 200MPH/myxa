<main>
    <section class="hero">
        <span class="eyebrow">Myxa Framework</span>

        <div class="hero-shell">
            <div class="logo-card">
                <img class="logo" src="<?= $_e($logoPath) ?>" alt="Myxa logo">
            </div>

            <div class="stack">
                <h1><?= $_e($appName) ?> is running :-)</h1>
                <p class="lede">
                    A lean Myxa application is now booted through the framework container, service
                    providers, router, and controller pipeline with the full app runtime in charge.
                </p>

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
                        <strong>Version</strong>
                        <?= $_e($appVersion) ?> <small>(<?= $_e($versionSource) ?>)</small>
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
            </div>
        </div>
    </section>
</main>
