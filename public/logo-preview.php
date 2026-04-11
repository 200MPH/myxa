<?php

declare(strict_types=1);

$logos = [
    [
        'title' => 'Current',
        'file' => '/assets/images/myxa-logo.svg',
        'note' => 'Balanced between organic and technical.',
    ],
    [
        'title' => 'Alternative A',
        'file' => '/assets/images/myxa-logo-alt-geometric.svg',
        'note' => 'More minimal and architectural.',
    ],
    [
        'title' => 'Alternative B',
        'file' => '/assets/images/myxa-logo-alt-organic.svg',
        'note' => 'Leans harder into the mycelium idea.',
    ],
    [
        'title' => 'Alternative C',
        'file' => '/assets/images/myxa-logo-alt-circuit.svg',
        'note' => 'Sharper, more framework-and-code oriented.',
    ],
];

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Myxa Logo Preview</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", sans-serif;
            --ink: #0f172a;
            --muted: #475569;
            --card: rgba(255, 255, 255, 0.8);
            --line: rgba(15, 23, 42, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.18), transparent 30%),
                radial-gradient(circle at bottom right, rgba(132, 204, 22, 0.18), transparent 30%),
                linear-gradient(180deg, #f8fafc, #e2e8f0);
            color: var(--ink);
        }

        main {
            width: min(1180px, calc(100% - 2rem));
            margin: 0 auto;
            padding: 3rem 0 4rem;
        }

        h1 {
            margin: 0 0 0.75rem;
            font-size: clamp(2rem, 5vw, 3.75rem);
            line-height: 1.02;
        }

        p.lead {
            max-width: 700px;
            margin: 0 0 2rem;
            color: var(--muted);
            font-size: 1.05rem;
        }

        .grid {
            display: grid;
            gap: 1rem;
        }

        .card {
            padding: 1.25rem;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: var(--card);
            backdrop-filter: blur(10px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .card h2 {
            margin: 0 0 0.25rem;
            font-size: 1.1rem;
        }

        .card p {
            margin: 0 0 1rem;
            color: var(--muted);
        }

        .logo-wrap {
            padding: 1rem;
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(255,255,255,0.82), rgba(241,245,249,0.95));
            overflow: auto;
        }

        .logo-wrap img {
            display: block;
            width: min(100%, 560px);
            height: auto;
        }
    </style>
</head>
<body>
    <main>
        <h1>Myxa logo alternatives</h1>
        <p class="lead">These are side-by-side explorations only. The current logo remains the one used by the app until you choose a replacement.</p>
        <div class="grid">
            <?php foreach ($logos as $logo): ?>
                <section class="card">
                    <h2><?= esc($logo['title']) ?></h2>
                    <p><?= esc($logo['note']) ?></p>
                    <div class="logo-wrap">
                        <img src="<?= esc($logo['file']) ?>" alt="<?= esc($logo['title']) ?>">
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
