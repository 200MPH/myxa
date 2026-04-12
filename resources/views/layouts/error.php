<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_e($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $_e($faviconPath) ?>">
    <style>
        :root {
            color-scheme: dark;
            font-family: "Segoe UI", sans-serif;
            --page-bg: #07111f;
            --panel-bg: rgba(8, 18, 34, 0.84);
            --panel-border: rgba(148, 163, 184, 0.18);
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #f59e0b;
            --accent-soft: rgba(245, 158, 11, 0.14);
            --danger: #fb7185;
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
                radial-gradient(circle at top, rgba(245, 158, 11, 0.16), transparent 30%),
                radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.18), transparent 36%),
                linear-gradient(180deg, #030712 0%, var(--page-bg) 100%);
            color: var(--text-main);
        }

        main {
            width: min(760px, 100%);
            padding: 2rem;
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            background: var(--panel-bg);
            box-shadow: 0 24px 80px rgba(2, 6, 23, 0.45);
            backdrop-filter: blur(16px);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #fde68a;
            font-size: 0.9rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1 {
            margin: 1rem 0 0.75rem;
            font-size: clamp(2rem, 5vw, 3.5rem);
            line-height: 1;
        }

        p {
            margin: 0;
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            margin-top: 1.5rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
        }

        .button-primary {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: #111827;
        }

        .button-secondary {
            border: 1px solid var(--panel-border);
            color: var(--text-main);
            background: rgba(15, 23, 42, 0.72);
        }

        .meta,
        .debug {
            margin-top: 1.5rem;
            padding: 1rem 1.1rem;
            border-radius: 18px;
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid var(--panel-border);
        }

        .meta strong,
        .debug strong {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--text-main);
        }

        .debug {
            border-color: rgba(251, 113, 133, 0.22);
        }

        pre {
            margin: 0.75rem 0 0;
            padding: 1rem;
            border-radius: 14px;
            overflow-x: auto;
            background: rgba(2, 6, 23, 0.88);
            color: #cbd5e1;
            font-size: 0.92rem;
            line-height: 1.45;
        }

        code {
            color: #fde68a;
        }
    </style>
</head>
<body>
<?= $body ?>
</body>
</html>
