<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Actions
 */
class Actions extends Component
{
    /**
     * Render the Actions content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="basics">Basics</h2>
            <p>Attach <code>fuse:click</code>, <code>fuse:submit</code>, and dynamic <code>fuse:*</code> listeners.</p>
            <h2 id="modifiers">Modifiers</h2>
            <p><code>.prevent</code>, <code>.stop</code>, <code>.once</code></p>
            <h2 id="keys">Key Bindings</h2>
            <p>Key aliases like <code>enter</code>, <code>escape</code>, etc.</p>
            <pre><code class="lang-html">&lt;button fuse:click.prevent="deletePost(42)">Delete&lt;/button>
&lt;input fuse:keydown.enter="search" />
</code></pre>
        </div>
HTML;
    }
}
