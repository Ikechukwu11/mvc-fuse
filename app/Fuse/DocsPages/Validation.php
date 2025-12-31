<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Validation
 */
class Validation extends Component
{
    /**
     * Render Validation content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="overview">Overview</h2>
            <p>Call <code>$this->validate(['field' => 'rules'])</code> to validate.</p>
            <p>Supported rules: <code>required</code>, <code>email</code>, <code>min:x</code>, <code>max:x</code>.</p>
            <pre><code class="lang-php">$this->validate([
  'email' => 'required|email',
  'name' => 'required|min:3|max:50',
]);
</code></pre>
        </div>
HTML;
    }
}
