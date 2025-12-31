<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Forms
 */
class Forms extends Component
{
    /**
     * Render Forms content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
            <h2 id="overview">Overview</h2>
            <p>Encapsulate properties and validation in Form classes extending <code>Engine\Fuse\Form</code>.</p>
            <p>Bind via dot notation like <code>form.name</code>.</p>
            <pre><code class="lang-php">class ProfileForm extends Engine\Fuse\Form {
  public string $name = '';
  public string $email = '';
  public array $rules = ['name' => 'required', 'email' => 'required|email'];
}
</code></pre>
        </div>
HTML;
    }
}
