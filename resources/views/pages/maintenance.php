<main>
    <span class="eyebrow">Maintenance mode</span>
    <h1>We&rsquo;re temporarily offline for maintenance.</h1>
    <p>
        The application is currently unavailable while we apply updates or finish background work.
        Please try again in a few minutes.
    </p>

    <?php if ($enabledAt !== null): ?>
    <section class="meta">
        <strong>Maintenance enabled at</strong>
        <code><?= $_e($enabledAt) ?></code>
    </section>
    <?php endif; ?>
</main>
