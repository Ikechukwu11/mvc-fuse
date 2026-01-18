<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-path" content="<?= config('app.base_path') ?>">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="theme-color" content="#007bff">
    <title><?= e($title ?? 'My MVC App') ?></title>
    <style>
        @view-transition {
            navigation: auto;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            margin: 0;
        }

        .card {
            margin-top: 80px;
            max-width: 720px;
            padding: 1.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .card h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
        }

        .sub {
            color: #555;
        }

        /* Fixed Header Styles */
        #header {
            position: absolute;
            top: 35px;
            left: 0;
            width: 100%;
            padding: 15px 0;
            text-align: center;
            color: white;
            font-size: 1.5rem;
            z-index: 1000;
            font-weight: bold;
        }

        #backButton {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        :root {
            --fuse-accent: <?= $randomColor ??  config('fuse.loading.color', '#4F46E5') ?>;
        }
    </style>
</head>

<body>
    <!-- Fixed Header -->
    <header id="header">
        <?= e($title ?? 'Page Title') ?>

        <?php if ($_SERVER['REQUEST_URI'] !== '/') : ?>
            <!-- Add Back Arrow Button if not on homepage -->
            <button id="backButton" onclick="window.history.back()" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                ← Back
            </button>
        <?php endif; ?>
        <button id="refreshButton" onclick="window.location.reload()" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: white; font-size: 0.9rem; padding: 6px 10px; border-radius: 6px; cursor: pointer;">
            Refresh
        </button>
    </header>
    <div class="card">
        <?= $content ?? '' ?>

    </div>
    <?php
    $randomColor = $randomColor ?? config('fuse.loading.color');
    echo fuse_scripts($randomColor)
    ?>
    <script>
        // Generate a random color for the header
        // function getRandomColor() {
        //     const letters = '0123456789ABCDEF';
        //     let color = '#';
        //     for (let i = 0; i < 6; i++) {
        //         color += letters[Math.floor(Math.random() * 16)];
        //     }
        //     if(color==='#FFF' || color==='#FFFFFF'){
        //         color==='#FFFDD0'
        //     }
        //     return color;
        // }

        let randomColor = <?= json_encode($randomColor) ?>;

        // Set random background color for the header
        document.getElementById('header').style.backgroundColor = randomColor;
    </script>
</body>

</html>