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
            width: min(960px, 100%);
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
            padding: clamp(1rem, 3vw, 1.5rem);
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
            width: min(100%, 460px);
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

        @media (min-width: 860px) {
            .hero-shell {
                grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
                align-items: center;
            }
        }
    </style>
</head>
<body>
<?= $body ?>
</body>
</html>
