<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-path" content="<?= config('app.base_path') ?>">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'My MVC App') ?></title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 2rem; }
        .card { max-width: 720px; padding: 1.5rem; border: 1px solid #ddd; border-radius: 8px; }
        .card h1 { margin: 0 0 0.5rem 0; font-size: 1.5rem; }
        .sub { color: #555; }
    </style>
</head>
<body>
    <div class="card">
        <?= $content ?? '' ?>
    </div>
    <?= fuse_scripts() ?>
</body>
</html>

