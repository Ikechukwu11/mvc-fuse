<style>
    .fuse-nav {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-top: 1.5rem;
    }

    .fuse-nav a {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.6rem 0.8rem;
        background: color-mix(in srgb, var(--fuse-accent) 70%, black);
        color: #fff;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 500;
        transition: all .2s ease;
        font-size: 32px;
        font-weight: bold;
    }

    .fuse-nav a:hover {
        background: color-mix(in srgb, var(--fuse-accent) 95%, white);
        transform: translateY(-2px);
    }

    .fuse-nav span {
        font-size: 0.85rem;
    }

    /* Mobile */
    @media (max-width: 480px) {
        .fuse-nav {
            grid-template-columns: repeat(2, 1fr);
        }

        .fuse-nav a {
            flex-direction: column;
            padding: 0.8rem 0.5rem;
        }

        .fuse-nav span {
            font-size: 0.75rem;
        }
    }
</style>

<h1><?= e($title ?? 'Fuse Home') ?></h1>
<p class="sub"><?= e($message ?? '') ?></p>

<nav class="fuse-nav">
    <a href="<?= route('/fuse/docs') ?>" fuse:naviga>📘 <span>Docs</span></a>
    <a href="<?= route('/fuse/native') ?>" fuse:naviga>⚙️ <span>Native</span></a>
    <a href="<?= route('/fuse/test') ?>" fuse:naviga>🧪 <span>Test</span></a>
    <a href="<?= route('/fuse/music') ?>" fuse:naviga>🎵 <span>Music</span></a>
    <a href="<?= route('/fuse/login') ?>" fuse:naviga>🔐 <span>Login</span></a>
    <a href="<?= route('/fuse/register') ?>" fuse:naviga>📝 <span>Register</span></a>
    <a href="<?= route('/fuse/profile') ?>" fuse:naviga>👤 <span>Profile</span></a>
</nav>