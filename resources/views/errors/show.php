<main>
    <span class="eyebrow">Error <?= $_e($status) ?></span>
    <h1><?= $_e($heading) ?></h1>
    <p><?= $_e($message) ?></p>

    <div class="actions">
        <a class="button button-primary" href="<?= $_e($homePath) ?>">Back home</a>
        <a class="button button-secondary" href="javascript:history.back()">Go back</a>
    </div>

    <section class="meta">
        <strong>Error type</strong>
        <code><?= $_e($errorType) ?></code>
    </section>

    <?php if ($debug): ?>
    <section class="debug">
        <strong><?= $_e($debugData['exception']) ?></strong>
        <p>Request: <?= $_e($debugData['request']) ?></p>
        <p>File: <?= $_e(sprintf('%s:%s', (string) $debugData['file'], (string) $debugData['line'])) ?></p>
        <?php if ($debugData['message'] !== ''): ?>
        <p>Message:</p>    
        <p><?= $_e($debugData['message']) ?></p>
        <?php endif; ?>
        <pre><?= $_e(implode("\n", $debugData['trace'])) ?></pre>
    </section>
    <?php endif; ?>
</main>
