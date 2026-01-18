<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Pagination
 */
class Pagination extends Component
{
    public bool $lazy = false;
    /**
     * Render Pagination docs with a live demo.
     *
     * @return string
     */
    public function render()
    {
        $demo = fuse(\App\Fuse\RandomUsers::class);

        $componentExample =
            <<<'PHP'
            class RandomUsers extends Component {
                public array $users = [];
                public int $page = 1;
                public int $perPage = 5;

                public function mount() {
                    // Build $users with custom/random data
                }
                public function prev() { $this->page = max(1, $this->page - 1); }
                public function next() { $this->page = min($this->pages(), $this->page + 1); }
                public function go(int $p) { $this->page = max(1, min($this->pages(), $p)); }
                public function pages(): int { return max(1, (int)ceil(count($this->users)/$this->perPage)); }
                public function currentSlice(): array {
                    $offset = ($this->page - 1) * $this->perPage;
                    return array_slice($this->users, $offset, $this->perPage);
                }
            }
            PHP;

        $usageExample = <<<'HTML'
    <div>
    <div fuse:loading-target=".loading-indicator" style="display:flex; gap:8px; align-items:center;">
        <span class="loading-indicator" style="display:none;">Loading…</span>
        <button fuse:click="pagePrev">Prev</button>
        <!-- page links -->
        <button fuse:click="pageNext">Next</button>
    </div>
    </div>
    HTML;

        return <<<HTML
        <div>
            <h2 id="overview">Overview</h2>
            <p>Fuse does not require a database to paginate UI. Manage <code>page</code> and <code>perPage</code> in your component and slice data server-side.</p>

            <h2 id="demo">Live Demo</h2>
            {$demo}

            <h2 id="component">Component</h2>
            <pre><code class="lang-php">{$componentExample}</code></pre>

            <h2 id="usage">Usage Markup</h2>
            <pre><code class="lang-html">{$usageExample}</code></pre>
            <p>Tip: Bind <code>perPage</code> with <code>fuse:model.number</code> and let the component normalize and reset page during <code>rendering()</code>. No full-page reload.</p>
        </div>
HTML;
    }
}
