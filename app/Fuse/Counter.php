<?php

namespace App\Fuse;

use Engine\Fuse\Component;

/**
 * Counter Component
 *
 * A simple demonstration of a Fuse component with state and actions.
 */
class Counter extends Component
{
    /**
     * @var int The current count
     */
    public int $count = 0;

    /**
     * Increment the counter.
     */
    public function increment()
    {
        $this->count++;
    }

    /**
     * Decrement the counter.
     */
    public function decrement()
    {
        if ($this->count > 0)
            $this->count--;
    }

    /**
     * Render the component view.
     *
     * @return string
     */
    public function render()
    {
        return <<<HTML
        <div style="text-align: center; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
            <h2>Counter Component</h2>
            <h1 style="font-size: 3em;">{$this->count}</h1>
            <button fuse:click="decrement" style="padding: 10px 20px;">-</button>
            <button fuse:click="increment" style="padding: 10px 20px;">+</button>
        </div>
HTML;
    }
}
