<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Events
 */
class Events extends Component
{
    /**
     * Render Events content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="overview">Overview</h2>
            <p>Dispatch browser events using <code>$this->dispatch('event-name', $detail)</code>.</p>
            <p>The JS client re-dispatches them as <code>CustomEvent</code> on <code>window</code>.</p>
            <pre><code class="lang-php">$this->dispatch('profile-updated', ['name' => $this->name]);
</code></pre>
        </div>
HTML;
    }
}
