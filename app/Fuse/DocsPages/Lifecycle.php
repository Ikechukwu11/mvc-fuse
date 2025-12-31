<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Lifecycle Hooks
 */
class Lifecycle extends Component
{
    /**
     * Render Lifecycle content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <p>Hooks: <code>boot</code>, <code>mount</code>, <code>hydrated</code>, <code>dehydrated</code>, <code>rendering</code>, <code>rendered</code>, <code>exception</code></p>
            <h2 id="boot">boot</h2>
            <pre><code class="lang-php">public function boot() {}
public function mount() {}
public function rendering() {}
public function rendered() {}
public function exception(\Throwable $e, bool &$stopPropagation = false) {}
</code></pre>
            <h2 id="mount">mount</h2>
            <pre><code class="lang-php">public function mount() { /* prepare state */ }</code></pre>
            <h2 id="hydrated">hydrated</h2>
            <pre><code class="lang-php">public function hydrated() { /* after request hydration */ }</code></pre>
            <h2 id="dehydrated">dehydrated</h2>
            <pre><code class="lang-php">public function dehydrated() { /* before response dehydration */ }</code></pre>
            <h2 id="rendering">rendering</h2>
            <pre><code class="lang-php">public function rendering() { /* before render */ }</code></pre>
            <h2 id="rendered">rendered</h2>
            <pre><code class="lang-php">public function rendered() { /* after render */ }</code></pre>
            <h2 id="exception">exception</h2>
            <pre><code class="lang-php">public function exception(\Throwable $e, bool &$stopPropagation = false) { /* handle */ }</code></pre>
        </div>
HTML;
    }
}
