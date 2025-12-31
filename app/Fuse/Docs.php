<?php

namespace App\Fuse;

use Engine\Fuse\Component;

/**
 * Fuse Documentation Component
 *
 * Renders a responsive documentation page with navbar, sidebar, content area,
 * and live search powered by Fuse events.
 */
class Docs extends Component
{
    /**
     * Layout used for full-page rendering.
     *
     * @var string
     */
    protected string $layout = 'layouts/docs';
    /**
     * The current search query.
     *
     * @var string
     */
    public string $query = '';

    /**
     * The currently selected section slug.
     *
     * @var string
     */
    public string $current = 'introduction';

    /**
     * All documentation sections.
     *
     * @var array
     */
    public array $sections = [];

    /**
     * Search behavior: 'debounce' or 'blur'.
     *
     * @var string
     */
    public string $searchMode = 'debounce';

    /**
     * Debounce duration in milliseconds.
     *
     * @var int
     */
    public int $debounceMs = 300;

    /**
     * Mobile menu open/closed state.
     *
     * @var bool
     */
    public bool $menuOpen = false;

    /**
     * Initialize documentation sections.
     *
     * @return void
     */
    public function mount()
    {
        // Respect initial section if provided (e.g., from querystring via route)
        if (empty($this->current)) {
            $this->current = 'introduction';
        }

        $this->sections = [
            [
                'slug' => 'introduction',
                'title' => 'Introduction',
                'component' => \App\Fuse\DocsPages\Introduction::class,
                'keywords' => 'intro getting-started overview',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                ],
            ],
            [
                'slug' => 'getting-started',
                'title' => 'Getting Started',
                'component' => \App\Fuse\DocsPages\GettingStarted::class,
                'keywords' => 'install setup fuse helper renderPage',
                'anchors' => [
                    ['id' => 'fuse-helper', 'title' => 'Using fuse()'],
                    ['id' => 'render-page', 'title' => 'Full-Page Components'],
                ],
            ],
            [
                'slug' => 'actions',
                'title' => 'Actions',
                'component' => \App\Fuse\DocsPages\Actions::class,
                'keywords' => 'click submit events modifiers keydown',
                'anchors' => [
                    ['id' => 'basics', 'title' => 'Basics'],
                    ['id' => 'modifiers', 'title' => 'Modifiers'],
                    ['id' => 'keys', 'title' => 'Key Bindings'],
                ],
            ],
            [
                'slug' => 'lifecycle',
                'title' => 'Lifecycle Hooks',
                'component' => \App\Fuse\DocsPages\Lifecycle::class,
                'keywords' => 'boot mount hydrated dehydrated rendering rendered exception',
                'anchors' => [
                    ['id' => 'boot', 'title' => 'boot'],
                    ['id' => 'mount', 'title' => 'mount'],
                    ['id' => 'hydrated', 'title' => 'hydrated'],
                    ['id' => 'dehydrated', 'title' => 'dehydrated'],
                    ['id' => 'rendering', 'title' => 'rendering'],
                    ['id' => 'rendered', 'title' => 'rendered'],
                    ['id' => 'exception', 'title' => 'exception'],
                ],
            ],
            [
                'slug' => 'navigate',
                'title' => 'Navigate',
                'component' => \App\Fuse\DocsPages\Navigate::class,
                'keywords' => 'spa links prefetch scroll hooks persist',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                    ['id' => 'hover', 'title' => 'Hover Prefetch'],
                    ['id' => 'persist', 'title' => 'Element Persistence'],
                ],
            ],
            [
                'slug' => 'pagination',
                'title' => 'Pagination',
                'component' => \App\Fuse\DocsPages\Pagination::class,
                'keywords' => 'paginate page perPage slice listing',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                    ['id' => 'demo', 'title' => 'Live Demo'],
                    ['id' => 'component', 'title' => 'Component'],
                    ['id' => 'usage', 'title' => 'Usage Markup'],
                ],
            ],
            [
                'slug' => 'lazy',
                'title' => 'Lazy Loading',
                'component' => \App\Fuse\DocsPages\Lazy::class,
                'keywords' => 'lazy placeholder viewport on-load',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                ],
            ],
            [
                'slug' => 'validation',
                'title' => 'Validation',
                'component' => \App\Fuse\DocsPages\Validation::class,
                'keywords' => 'validate rules errors',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                ],
            ],
            [
                'slug' => 'forms',
                'title' => 'Form Objects',
                'component' => \App\Fuse\DocsPages\Forms::class,
                'keywords' => 'form object binding dot notation',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                ],
            ],
            [
                'slug' => 'events',
                'title' => 'Browser Events',
                'component' => \App\Fuse\DocsPages\Events::class,
                'keywords' => 'dispatch browser events CustomEvent',
                'anchors' => [
                    ['id' => 'overview', 'title' => 'Overview'],
                ],
            ],
        ];
    }

    /**
     * Update search results when typing.
     *
     * @return void
     */
    public function search()
    {
        // No-op: render() will use $query to filter in-place.
    }

    /**
     * Switch the current section.
     *
     * @param string $slug
     * @return void
     */
    public function go(string $slug)
    {
        $this->current = $slug;
        $this->dispatch('docs-section-changed', ['slug' => $slug]);
    }

    /**
     * Render the documentation UI.
     *
     * @return string
     */
    public function render()
    {
        $q = mb_strtolower(trim($this->query));
        $filtered = array_values(array_filter($this->sections, function ($sec) use ($q) {
            if ($q === '') return true;
            $hay = mb_strtolower($sec['title'] . ' ' . ($sec['keywords'] ?? ''));
            return strpos($hay, $q) !== false;
        }));

        $sidebarItems = '';
        foreach ($filtered as $sec) {
            $active = $sec['slug'] === $this->current ? 'background:#e9edff; color:#1d2dd9; font-weight:600;' : '';
            $title = htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8');
            $sidebarItems .= <<<HTML
            <div class="docs-sidebar-item" style="margin-bottom:10px;">
                <button fuse:click="go('{$sec['slug']}')" style="display:block; width:100%; text-align:left; padding:10px; border:none; background:none; cursor:pointer; border-radius:8px; {$active}">
                    {$title}
                </button>
HTML;
            if (!empty($sec['anchors'])) {
                $anchorsHtml = '';
                foreach ($sec['anchors'] as $anc) {
                    $ancTitle = htmlspecialchars($anc['title'], ENT_QUOTES, 'UTF-8');
                    $ancId = htmlspecialchars($anc['id'], ENT_QUOTES, 'UTF-8');
                    $href = "/docs?section={$sec['slug']}#{$ancId}";
                    $anchorsHtml .= <<<HTML
                    <a href="{$href}" class="docs-anchor-link" fuse:navigate.hover style="display:block; padding:6px 10px; margin-left:8px; border-radius:6px; color:#334; text-decoration:none;">{$ancTitle}</a>
HTML;
                }
                $sidebarItems .= <<<HTML
                <div class="docs-anchors" style="margin-top:4px;">{$anchorsHtml}</div>
HTML;
            }
            $sidebarItems .= "</div>";
        }

        $currentSec = array_values(array_filter($this->sections, fn($s) => $s['slug'] === $this->current))[0] ?? $this->sections[0];
        $contentTitle = htmlspecialchars($currentSec['title'], ENT_QUOTES, 'UTF-8');
        $contentBody = fuse($currentSec['component']);

        $inputAttrs = $this->searchMode === 'blur'
            ? 'fuse:model="query" fuse:blur="search"'
            : 'fuse:model="query" fuse:input.debounce.' . (int) $this->debounceMs . '="search"';

        return <<<HTML
        <div style="display:flex; flex-direction:column; min-height:100vh;">
            <header style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #e7e9f3; position:sticky; top:0; background:linear-gradient(180deg,#ffffff,#f6f7ff); z-index:10;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <a href="/" fuse:navigate style="text-decoration:none; color:#1d2dd9; font-weight:800;">Fuse</a>
                    <span style="color:#5a61ea; font-weight:600;">Docs</span>
                </div>
                <div style="flex:1; max-width:560px; margin:0 12px;">
                    <input type="search" {$inputAttrs} placeholder="Search docs..." style="width:100%; padding:10px 12px; border:1px solid #cfd3ff; border-radius:10px; outline-color:#4F46E5;">
                </div>
            </header>

            <main style="display:grid; grid-template-columns:280px 1fr; gap:18px; padding:18px;">
                <aside style="position:sticky; top:64px; align-self:start;">
                    <div style="border:1px solid #e7e9f3; border-radius:12px; padding:12px; background:#ffffff; position:relative;">
                        <button aria-label="Toggle Menu" style="position:absolute; top:10px; right:10px; display:none; padding:8px 10px; border:1px solid #cfd3ff; border-radius:8px; background:#fff; color:#1d2dd9;" class="docs-hamburger">☰</button>
                        <div style="margin-bottom:8px; padding:10px; border-radius:8px; background:#f6f7ff; color:#1d2dd9; font-weight:600;">
                            {$contentTitle}
                        </div>
                        <div class="docs-menu">
                            {$sidebarItems}
                        </div>
                    </div>
                </aside>
                <section>
                    <article style="border:1px solid #e7e9f3; border-radius:12px; padding:20px; background:#ffffff; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                        <h1 style="margin:0 0 12px 0;">{$contentTitle}</h1>
                        <div style="color:#222; line-height:1.7;">{$contentBody}</div>
                    </article>
                </section>
            </main>

            <footer style="margin-top:auto; padding:16px; border-top:1px solid #e7e9f3; color:#555; text-align:center; background:#f6f7ff;">
                Built with Fuse — server-driven components for vanilla PHP.
            </footer>
        </div>
HTML;
    }
}
