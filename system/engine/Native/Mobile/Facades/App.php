<?php

namespace Native\Mobile\Facades;

use Native\Mobile\Native;

class App
{
    /**
     * Set status bar color and style.
     *
     * @param string|null $color Hex color (e.g., "#FF0000")
     * @param string|null $style "light", "dark", or "auto"
     * @param bool $overlay Whether to overlay content (transparent status bar logic)
     */
    public static function setStatusBar(?string $color = null, ?string $style = null, bool $overlay = true): void
    {
        Native::call('App.SetStatusBar', [
            'color' => $color,
            'style' => $style,
            'overlay' => $overlay
        ]);
    }
}
