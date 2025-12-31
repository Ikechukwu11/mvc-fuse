<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Getting Started
 */
class GettingStarted extends Component
{
    /**
     * Render the Getting Started content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="fuse-helper">Using fuse()</h2>
            <p>Use <code>fuse()</code> helper to render components in views.</p>
            <h2 id="render-page">Full-Page Components</h2>
            <p>Define a route and call <code>Component::renderPage()</code> to render full pages.</p>
            <pre><code class="lang-php">&lt;?php
// In a view file
echo fuse(App\Fuse\TodoList::class, ['lazy' => false]);

// Full page via route closure
$router->get('/todo-app', function() {
    return App\Fuse\TodoList::renderPage();
});
</code></pre>
        </div>
HTML;
    }
}
