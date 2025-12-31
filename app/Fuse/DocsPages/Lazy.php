<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Lazy Loading
 */
class Lazy extends Component
{
    /**
     * Render Lazy content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="overview">Overview</h2>
            <p>Enable <code>$lazy</code> to render placeholders and lazy-load components.</p>
            <p>Supports full-page lazy placeholders via <code>Component::renderPage()</code>.</p>
            <pre><code class="lang-php">class UserProfile extends Component {
    public bool $lazy = true;
    public function placeholder(array $params = []) { return '&lt;div class="skeleton">...&lt;/div>'; }
}
</code></pre>
        </div>
HTML;
    }
}
