<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Navigate
 */
class Navigate extends Component
{
    /**
     * Render Navigate content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="overview">Overview</h2>
            <p>Use <code>fuse:navigate</code> for SPA-like links.</p>
            <p>Features: hover prefetch, scroll preservation, JS hooks, element persistence via <code>fuse:persist</code>.</p>
            <h2 id="hover">Hover Prefetch</h2>
            <pre><code class="lang-html">&lt;a href="/profile" fuse:navigate>Profile&lt;/a>
&lt;a href="/dashboard" fuse:navigate.hover>Dashboard&lt;/a>
&lt;div fuse:persist="player">&lt;audio ...>&lt;/audio>&lt;/div>
</code></pre>
            <h2 id="persist">Element Persistence</h2>
            <p>Persist elements across navigations to avoid reloading media or stateful widgets.</p>
        </div>
HTML;
    }
}
