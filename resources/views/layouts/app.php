<?php

declare(strict_types=1);

$metaDescription = isset($metaDescription) ? trim((string) $metaDescription) : '';
$metaUrl = isset($metaUrl) ? trim((string) $metaUrl) : '';
$metaImage = isset($metaImage) ? trim((string) $metaImage) : '';
$metaImageAlt = isset($metaImageAlt) ? trim((string) $metaImageAlt) : '';
$metaImageWidth = isset($metaImageWidth) ? trim((string) $metaImageWidth) : '';
$metaImageHeight = isset($metaImageHeight) ? trim((string) $metaImageHeight) : '';
$metaSiteName = isset($metaSiteName) ? trim((string) $metaSiteName) : '';
$metaType = isset($metaType) ? trim((string) $metaType) : 'website';
$twitterCard = isset($twitterCard) ? trim((string) $twitterCard) : 'summary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_e($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $_e($faviconPath) ?>">
    <?php if ($metaDescription !== '') : ?>
    <meta name="description" content="<?= $_e($metaDescription) ?>">
    <meta property="og:description" content="<?= $_e($metaDescription) ?>">
    <meta name="twitter:description" content="<?= $_e($metaDescription) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= $_e($title) ?>">
    <meta name="twitter:title" content="<?= $_e($title) ?>">
    <meta property="og:type" content="<?= $_e($metaType) ?>">
    <meta name="twitter:card" content="<?= $_e($twitterCard) ?>">
    <?php if ($metaSiteName !== '') : ?>
    <meta property="og:site_name" content="<?= $_e($metaSiteName) ?>">
    <?php endif; ?>
    <?php if ($metaUrl !== '') : ?>
    <link rel="canonical" href="<?= $_e($metaUrl) ?>">
    <meta property="og:url" content="<?= $_e($metaUrl) ?>">
    <?php endif; ?>
    <?php if ($metaImage !== '') : ?>
    <meta property="og:image" content="<?= $_e($metaImage) ?>">
    <meta name="twitter:image" content="<?= $_e($metaImage) ?>">
    <?php if ($metaImageWidth !== '') : ?>
    <meta property="og:image:width" content="<?= $_e($metaImageWidth) ?>">
    <?php endif; ?>
    <?php if ($metaImageHeight !== '') : ?>
    <meta property="og:image:height" content="<?= $_e($metaImageHeight) ?>">
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($metaImageAlt !== '') : ?>
    <meta property="og:image:alt" content="<?= $_e($metaImageAlt) ?>">
    <meta name="twitter:image:alt" content="<?= $_e($metaImageAlt) ?>">
    <?php endif; ?>
    <style>
        :root {
            color-scheme: dark;
            font-family: "Segoe UI", sans-serif;
            --page-bg: #07111f;
            --panel-bg: rgba(8, 18, 34, 0.84);
            --panel-border: rgba(148, 163, 184, 0.18);
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #14b8a6;
            --accent-2: #84cc16;
            --accent-soft: rgba(20, 184, 166, 0.16);
            --surface-light: linear-gradient(135deg, rgba(248, 250, 252, 0.98), rgba(226, 232, 240, 0.9));
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background:
                radial-gradient(circle at top, rgba(20, 184, 166, 0.18), transparent 28%),
                radial-gradient(circle at bottom right, rgba(132, 204, 22, 0.14), transparent 32%),
                linear-gradient(180deg, #030712 0%, var(--page-bg) 100%);
            color: var(--text-main);
        }

        main {
            width: min(1160px, 100%);
            padding: clamp(1.4rem, 3vw, 2.4rem);
            border: 1px solid var(--panel-border);
            border-radius: 30px;
            background: var(--panel-bg);
            box-shadow: 0 24px 80px rgba(2, 6, 23, 0.45);
            backdrop-filter: blur(16px);
        }

        .hero {
            display: grid;
            gap: 1.5rem;
        }

        .hero-brand {
            display: grid;
            gap: 1rem;
            justify-items: start;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #99f6e4;
            font-size: 0.9rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hero-shell {
            display: grid;
            gap: 1.4rem;
        }

        .logo-card {
            position: relative;
            overflow: hidden;
            width: min(100%, 360px);
            padding: clamp(0.85rem, 2.5vw, 1.25rem);
            border-radius: 24px;
            background: var(--surface-light);
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.7),
                0 20px 45px rgba(15, 23, 42, 0.3);
        }

        .logo-card::before {
            content: "";
            position: absolute;
            inset: auto -10% -40% auto;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.2), transparent 65%);
            pointer-events: none;
        }

        .logo {
            display: block;
            width: min(100%, 300px);
            margin: 0 auto;
            filter:
                drop-shadow(0 18px 30px rgba(15, 23, 42, 0.16))
                drop-shadow(0 0 22px rgba(20, 184, 166, 0.18));
        }

        h1 {
            margin: 0;
            font-size: clamp(2.4rem, 5vw, 4.4rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        p {
            margin: 0;
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.65;
        }

        .lede {
            max-width: 40rem;
            font-size: 1.08rem;
        }

        .stack {
            display: grid;
            gap: 1rem;
        }

        .hero-copy {
            max-width: 42rem;
        }

        .docs-page {
            display: grid;
            gap: 1.5rem;
        }

        .docs-brand {
            display: grid;
            gap: 1.25rem;
            padding: 1.1rem;
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(20, 184, 166, 0.16), transparent 38%),
                linear-gradient(135deg, rgba(248, 250, 252, 0.06), rgba(15, 23, 42, 0.08));
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .docs-brand-mark {
            display: grid;
            place-items: center;
            width: min(100%, 320px);
            padding: 0.9rem;
            border-radius: 22px;
            background: var(--surface-light);
            border: 1px solid rgba(255, 255, 255, 0.75);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.7),
                0 20px 45px rgba(15, 23, 42, 0.2);
        }

        .docs-brand-link {
            color: inherit;
            text-decoration: none;
        }

        .docs-brand-mark img {
            display: block;
            width: min(100%, 240px);
            filter:
                drop-shadow(0 16px 24px rgba(15, 23, 42, 0.14))
                drop-shadow(0 0 20px rgba(20, 184, 166, 0.16));
        }

        .docs-brand-copy {
            display: grid;
            gap: 0.85rem;
            max-width: 44rem;
        }

        .docs-brand-note {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.7;
        }

        .docs-brand-version {
            color: #fde68a;
            font-weight: 700;
        }

        .docs-header {
            display: grid;
            gap: 0.85rem;
        }

        .docs-shell {
            display: grid;
            gap: 1.25rem;
        }

        .docs-sidebar,
        .docs-content {
            padding: 1rem 1.05rem;
            border-radius: 20px;
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid var(--panel-border);
        }

        .docs-sidebar strong {
            display: block;
            margin-bottom: 0.8rem;
            color: var(--text-main);
        }

        .docs-nav {
            display: grid;
            gap: 0.45rem;
        }

        .docs-nav-link {
            display: block;
            padding: 0.7rem 0.8rem;
            border-radius: 14px;
            color: var(--text-muted);
            text-decoration: none;
            transition: background 120ms ease, color 120ms ease;
        }

        .docs-nav-link:hover,
        .docs-nav-link.is-active {
            background: rgba(20, 184, 166, 0.12);
            color: #99f6e4;
        }

        .docs-prose {
            display: grid;
            gap: 1rem;
        }

        .docs-prose h1,
        .docs-prose h2,
        .docs-prose h3,
        .docs-prose h4,
        .docs-prose h5,
        .docs-prose h6 {
            margin: 0;
            line-height: 1.1;
            letter-spacing: -0.03em;
        }

        .docs-prose h1 {
            font-size: clamp(2rem, 4vw, 3rem);
        }

        .docs-prose h2 {
            margin-top: 0.5rem;
            font-size: clamp(1.4rem, 3vw, 2rem);
        }

        .docs-prose h3 {
            font-size: 1.2rem;
        }

        .docs-prose p,
        .docs-prose li,
        .docs-prose blockquote {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.7;
        }

        .docs-prose ul,
        .docs-prose ol {
            margin: 0;
            padding-left: 1.25rem;
        }

        .docs-prose li + li {
            margin-top: 0.35rem;
        }

        .docs-prose pre,
        .docs-prose code {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        }

        .docs-prose code {
            padding: 0.12rem 0.35rem;
            border-radius: 8px;
            background: rgba(2, 6, 23, 0.8);
            color: #fde68a;
        }

        .docs-prose pre {
            margin: 0;
            padding: 1rem;
            overflow-x: auto;
            border-radius: 18px;
            background: rgba(2, 6, 23, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.14);
        }

        .docs-prose pre code {
            padding: 0;
            background: transparent;
            color: #cbd5e1;
        }

        .docs-prose blockquote {
            margin: 0;
            padding: 0.85rem 1rem;
            border-left: 4px solid rgba(20, 184, 166, 0.48);
            border-radius: 0 14px 14px 0;
            background: rgba(20, 184, 166, 0.08);
        }

        ul {
            display: grid;
            gap: 0.85rem;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        li {
            padding: 0.95rem 1rem;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid var(--panel-border);
        }

        strong {
            display: block;
            margin-bottom: 0.32rem;
            color: var(--text-main);
            font-size: 0.94rem;
        }

        a {
            color: #99f6e4;
        }

        @media (max-width: 859px) {
            body {
                display: block;
                padding: 0.85rem;
            }

            main {
                border-radius: 24px;
                padding: 1rem;
            }

            .docs-page {
                gap: 1rem;
            }

            .docs-brand,
            .docs-sidebar,
            .docs-content {
                padding: 0.9rem;
                border-radius: 18px;
            }

            .docs-brand {
                gap: 1rem;
            }

            .docs-brand-link,
            .docs-brand-mark {
                width: 100%;
            }

            .docs-brand-mark img {
                width: min(100%, 220px);
            }

            .docs-header {
                gap: 0.7rem;
            }

            .docs-shell {
                gap: 0.9rem;
            }

            .docs-sidebar strong {
                margin-bottom: 0.65rem;
            }

            .docs-nav {
                gap: 0.4rem;
            }

            .docs-nav-link {
                padding: 0.65rem 0.75rem;
            }

            .docs-prose h1 {
                font-size: clamp(1.6rem, 7vw, 2.1rem);
            }

            .docs-prose h2 {
                font-size: clamp(1.2rem, 5.5vw, 1.55rem);
            }

            .docs-prose p,
            .docs-prose li,
            .docs-prose blockquote {
                font-size: 0.98rem;
                line-height: 1.65;
            }

            .docs-prose pre {
                padding: 0.85rem;
                border-radius: 14px;
            }
        }

        @media (min-width: 860px) {
            .docs-brand {
                grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
                align-items: center;
            }

            .hero-shell {
                grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
                align-items: start;
            }

            .docs-shell {
                grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
                align-items: start;
            }

            .docs-sidebar {
                position: sticky;
                top: 1rem;
            }
        }
    </style>
</head>
<body>
<?= $body ?>
</body>
</html>
