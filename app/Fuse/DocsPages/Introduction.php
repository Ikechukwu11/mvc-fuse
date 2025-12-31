<?php

namespace App\Fuse\DocsPages;

use Engine\Fuse\Component;

/**
 * Docs Page: Introduction
 */
class Introduction extends Component
{
    /**
     * Render the Introduction content.
     *
     * @return string
     */
    public function render()
    {
        return <<<'HTML'
        <div>
    <h2 id="overview">Overview 🛞🐘🔥</h2>

    <p>
        <b>Fuse</b> is a wildly opinionated, proudly unnecessary,
        <i>“yes I rebuilt it from scratch”</i> server-driven component system for good old
        <b>vanilla PHP</b> 🍦🐘 – inspired by
        <a href="https://laravel.com/" target="_blank"><b style="color:#ff2d20">LARAVEL</b></a> 🚀 and
        <a href="https://livewire.laravel.com/" target="_blank"><b style="color:#F78C6C">LIVEWIRE</b></a> ⚡,
        but created purely because… well… <b>because I can.</b> 😎💪
    </p>

    <p>
        Is it reinventing the wheel? 🛞
        Absolutely. 💯
        <br>
        But this is not your regular wheel – this one is square ⬛, on fire 🔥, handcrafted in PHP 🐘,
        duct-taped together with caffeine ☕ and questionable life choices 🤪.
    </p>

    <p>
        Fuse is an <b>alpha-stage</b> 🧪, heavily opinionated 🗣️, totally unofficial 🚫,
        <b>custom Vanilla PHP MVC framework</b> 🐘🏗️ with its own
        <b>custom Livewire-for-Vanilla-PHP thingy called FUSE</b> ⚡🍦.
        <br>
        <br>
        It is built by <b>Kekesmovic</b> 👨‍💻 and his highly trained 🥷,
        occasionally rebellious 😈,
        slightly sarcastic 🤖 <b>AI goons</b> 👾👾👾.
    </p>

    <p>
        It delivers SPA-like navigation 🚀, server-side actions 🧠,
        validation 🛡️, lifecycle hooks 🔁,
        DOM-patching magic ✨🪄, and probably a few bugs 🐛 that think they are features 🎁 –
        all with <b>zero external dependencies maybe one for mailing I don't really remember</b> 🧹📦.
    </p>

    <p>
        Is this framework for everyone? Nada, Absolutely Not ❌ <br>
        Is it for me and my AI minions and <b>The Tinkerers <i>(Thou that tinkers)</i></b> ? Very yes. ✅😎🤖
    </p>

    <p>
        Use the sidebar to explore features 📚, examples 🧩, and more handcrafted wheels 🛞🛞🛞.
    </p>
</div>

HTML;
    }
}
