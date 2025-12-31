<h1><?= e($title ?? 'Home') ?></h1>
<p class="sub"><?= e($message ?? '') ?></p>

<p>
    <strong>Standard Web:</strong>
    <a href="<?= route('/profile') ?>">My Profile</a> |
    <a href="<?= route('/logout') ?>">Logout</a>
</p>

<p>
    <strong>Fuse (SPA):</strong>
    <a href="<?= route('/fuse-test') ?>" fuse:navigate>Test Page</a> |
    <a href="<?= route('/fuse/login') ?>" fuse:navigate>Login</a> |
    <a href="<?= route('/fuse/register') ?>" fuse:navigate>Register</a> |
    <a href="<?= route('/fuse/profile') ?>" fuse:navigate.hover>Profile</a>
</p>