<h1><?= e($title ?? 'Home') ?></h1>
<p class="sub"><?= e($message ?? '') ?></p>

<p>
    <strong>Standard Web:</strong>
    <a href="/profile">My Profile</a> |
    <a href="/logout">Logout</a>
</p>

<p>
    <strong>Fuse (SPA):</strong>
    <a href="/fuse-test" fuse:navigate>Test Page</a> |
    <a href="/fuse/login" fuse:navigate>Login</a> |
    <a href="/fuse/register" fuse:navigate>Register</a> |
    <a href="/fuse/profile" fuse:navigate.hover>Profile</a>
</p>