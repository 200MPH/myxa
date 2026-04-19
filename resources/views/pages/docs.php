<main class="docs-page">
    <section class="docs-brand">
        <a class="docs-brand-link" href="/" aria-label="Go to the home page">
            <div class="docs-brand-mark">
                <img src="/assets/images/myxa-logo.svg" alt="Myxa logo">
            </div>
        </a>

        <div class="docs-brand-copy">
            <h1>Myxa</h1>
            <p class="docs-brand-note">Version: <span class="docs-brand-version"><?= $_e($appVersion) ?></span></p>
            <p class="lede">
                A lightweight, AI-powered PHP framework for teams that want to build modern systems fast
                without giving up clarity, performance, or developer joy.
            </p>
            <p class="docs-brand-note">
                Myxa is designed around recent PHP practices: strict typing, explicit structure,
                container-driven architecture, fast backend execution, and a developer experience that
                feels approachable whether you stay server-rendered or grow into a hybrid frontend.
            </p>
        </div>
    </section>

    <section class="docs-header">
        <span class="eyebrow">Documentation</span>
    </section>

    <section class="docs-shell">
        <aside class="docs-sidebar">
            <strong>Guides</strong>
            <nav class="docs-nav">
                <?php foreach ($docs as $doc) : ?>
                <a
                    class="docs-nav-link<?= $doc['slug'] === $activeSlug ? ' is-active' : '' ?>"
                    href="/docs/<?= $_e($doc['slug']) ?>"
                >
                    <?= $_e($doc['title']) ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <article class="docs-content">
            <div class="docs-prose">
                <?= $content ?>
            </div>
        </article>
    </section>
</main>
