<h1>Fuse Test Page</h1>

<div style="margin-bottom: 20px;">
    This is a standard PHP view. Below are Fuse components.
</div>

<div style="display: flex; gap: 20px; flex-wrap: wrap;">
    <?= fuse('Counter') ?>
    <?= fuse('TodoList') ?>
</div>

<div style="margin-top: 20px;">
    <a href="/dashboard" fuse:navigate>Go to Dashboard (SPA Nav)</a> |
    <a href="/fuse-test" fuse:navigate>Reload This Page (SPA Nav)</a> |
    <a href="/todo-app" fuse:navigate>Go to Todo App (Full Page Component)</a>
</div>

<div style="margin-top: 20px;">
    <?= fuse_scripts() ?>
</div>
