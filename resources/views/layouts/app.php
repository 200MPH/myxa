<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_e($title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $_e($faviconPath) ?>">
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top, #f3efe4 0%, transparent 40%),
                linear-gradient(135deg, #0f172a 0%, #1f3a5f 45%, #7c5c3b 100%);
            color: #f8fafc;
        }

        main {
            width: min(720px, calc(100% - 2rem));
            padding: 2rem;
            border-radius: 24px;
            background: rgba(15, 23, 42, 0.78);
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(10px);
        }

        .logo {
            display: inline-block;
            width: min(100%, 340px);
            margin-bottom: 1rem;
            filter: drop-shadow(0 12px 28px rgba(15, 23, 42, 0.28));
        }

        h1 {
            margin-top: 0;
            font-size: clamp(2rem, 5vw, 3.5rem);
            line-height: 1.05;
        }

        p {
            color: #dbe4f0;
            font-size: 1.05rem;
        }

        ul {
            padding: 0;
            margin: 1.5rem 0 0;
            list-style: none;
        }

        li {
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
            border-radius: 14px;
            background: rgba(148, 163, 184, 0.12);
            border: 1px solid rgba(226, 232, 240, 0.12);
        }

        strong {
            display: block;
            margin-bottom: 0.2rem;
            color: #f8fafc;
        }

        a {
            color: #86efac;
        }
    </style>
</head>
<body>
<?= $body ?>
</body>
</html>
