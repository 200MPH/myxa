<main>
    <section class="hero">
        <div class="stack hero-copy">
            <span class="eyebrow">Vue hybrid test</span>
            <?php if (!$scaffolded) : ?>
            <h3>Frontend is not installed yet</h3>
            <p class="lede">
                Install the Vue hybrid frontend before using this test page.
            </p>
            <section class="meta">
                <strong>Install command</strong>
                <code><?= $_e($installCommand) ?></code>
            </section>
            <?php elseif (!$built) : ?>
            <h3>Frontend is installed but not built yet</h3>
            <p class="lede">
                Build the frontend bundle so the layout can load the generated JavaScript.
            </p>
            <section class="meta">
                <strong>Build command</strong>
                <code><?= $_e($buildCommand) ?></code>
            </section>
            <section class="meta">
                <strong>Watch command</strong>
                <code><?= $_e($watchCommand) ?></code>
            </section>
            <?php else : ?>
            <h3>Vue is mounted on this page</h3>
            <p class="lede">
                This route keeps the default home page server-rendered while giving the generated
                Vue/Vite bundle a dedicated place to prove it is loading.
            </p>
            <p data-vue-status>Vue bundle script has not loaded yet.</p>
            <div
                data-vue-component="CounterWidget"
                data-vue-props='<?= $_e(json_encode([
                    'title' => 'CounterWidget is live',
                    'initialCount' => 1,
                ], JSON_THROW_ON_ERROR)) ?>'
            >
                Vue bundle has not mounted this component yet.
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>
