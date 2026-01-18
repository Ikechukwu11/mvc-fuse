<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Device as DeviceFacade;

/**
 * Vibrate demo page for Fuse Native
 */
class DeviceVibrate extends BaseFuse
{
    /** Short vibrate */
    public function vibrateShort(): void
    {
        DeviceFacade::vibrate(50);
    }

    /** Long vibrate */
    public function vibrateLong(): void
    {
        DeviceFacade::vibrate(500);
    }

    /** Render page */
    public function render(): string
    {
        return <<<HTML
        <div style="padding: 20px;">
            <h2>Vibrate</h2>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                <button fuse:click="vibrateShort" style="padding: 12px; background: #4F46E5; color: white; border: none; border-radius: 6px;">Vibrate (Short)</button>
                <button fuse:click="vibrateLong" style="padding: 12px; background: #4338ca; color: white; border: none; border-radius: 6px;">Vibrate (Long)</button>
            </div>
            <div style="margin-top: 30px;">
                <a href="/fuse/native" fuse:naviga style="color: #4F46E5;">&larr; Back to Demo Menu</a>
            </div>
        </div>
HTML;
    }
}
