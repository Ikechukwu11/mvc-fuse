<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-path" content="<?= config('app.base_path') ?>">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($title ?? 'Fuse Docs') ?></title>
    <style>
        :root {
            --brand: #4F46E5;
            --brand-600: #5a61ea;
            --surface: #ffffff;
            --surface-alt: #f6f7ff;
            --border: #e7e9f3;
            --text: #111;
            --muted: #555;
            /* Vibrant code theme */
            --code-bg: linear-gradient(135deg, #0b1020 0%, #11173a 100%);
            --code-border: #18204a;
            --code-php: #ffd166;
            --code-html: #06d6a0;
            --code-badge: #1d2dd9;
            --code-shadow: 0 10px 25px rgba(29, 45, 217, 0.15);
            --anchor-active-bg: #e9edff;
            --anchor-active-color: #1d2dd9;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            margin: 0;
            background: linear-gradient(180deg, #fafafa, #f6f7ff);
            color: var(--text);
        }

        a {
            color: var(--brand);
        }

        pre {
            background: var(--code-bg);
            color: #e6edf7;
            padding: 14px 16px;
            border-radius: 12px;
            overflow: auto;
            border: 1px solid var(--code-border);
            box-shadow: var(--code-shadow);
        }

        code {
            /* background: #eef2ff; */
            color: #1d2dd9;
            padding: 2px 6px;
            border-radius: 6px;
        }

        /* Language badges and vibrant palette */
        code[class^="lang-"] {
            position: relative;
            display: block;
            padding-top: 28px;
            line-height: 1.6;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        code.lang-php {
            color: var(--code-php);
        }

        code.lang-html {
            color: var(--code-html);
        }

        code[class^="lang-"]::before {
            content: attr(class);
            position: absolute;
            top: 4px;
            right: 10px;
            font-size: 12px;
            background: #fff;
            color: var(--code-badge);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 2px 8px;
        }

        /* Sidebar anchors */
        .docs-anchor-link.active {
            background: var(--anchor-active-bg) !important;
            color: var(--anchor-active-color) !important;
            font-weight: 600;
        }

        @media (max-width: 900px) {
            main {
                grid-template-columns: 1fr !important;
            }

            aside {
                position: static !important;
            }

            .docs-hamburger {
                display: inline-block !important;
            }

            .docs-menu {
                display: none;
            }

            .docs-menu.open {
                display: block;
            }
        }
    </style>
</head>

<body>
    <?= $content ?? '' ?>
    <?= fuse_scripts() ?>
    <script>
        // Update querystring when section changes
        window.addEventListener('docs-section-changed', (e) => {
            const slug = e.detail?.slug || 'introduction';
            const url = new URL(window.location.href);
            url.searchParams.set('section', slug);
            window.history.replaceState({}, '', url.toString());
            // If hash exists after section change, scroll to it
            requestAnimationFrame(() => {
                const {
                    hash
                } = window.location;
                if (hash) {
                    const el = document.querySelector(hash);
                    if (el) el.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    updateActiveAnchor();
                }
            });
        });

        // Sync RandomUsers pagination state to URL for bookmarking/sharing
        window.addEventListener('random-users-state', (e) => {
            const page = e.detail?.page;
            const perPage = e.detail?.perPage;
            const url = new URL(window.location.href);
            if (typeof page === 'number') url.searchParams.set('page', String(page));
            if (typeof perPage === 'number') url.searchParams.set('perPage', String(perPage));
            window.history.replaceState({}, '', url.toString());
        });

        function updateActiveAnchor() {
            const {
                hash
            } = window.location;
            document.querySelectorAll('.docs-anchor-link').forEach(a => {
                if (hash && a.getAttribute('href')?.endsWith(hash)) {
                    a.classList.add('active');
                } else {
                    a.classList.remove('active');
                }
            });
        }

        // Toggle menu from aside hamburger (frontend only)
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.querySelector('.docs-hamburger');
            const menu = document.querySelector('.docs-menu');
            if (btn && menu) {
                btn.addEventListener('click', () => {
                    menu.classList.toggle('open');
                });
            }
            updateActiveAnchor();
            // Scroll to hash on initial load
            if (window.location.hash) {
                const el = document.querySelector(window.location.hash);
                if (el) el.scrollIntoView({
                    behavior: 'smooth',
                    block: "end",
                    inline: "nearest"
                });
            }
        });

        window.addEventListener('hashchange', () => {
            updateActiveAnchor();
            const el = document.querySelector(window.location.hash);
            if (el) el.scrollIntoView({
                behavior: 'smooth',
                block: "end",
                inline: "nearest"
            });
        });
    </script>
</body>

</html>