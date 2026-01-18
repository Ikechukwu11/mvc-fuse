<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\App as AppFacade;

class Index extends BaseFuse
{
    public function setStatusBarRed()
    {
        AppFacade::setStatusBar('#EF4444', 'light', true);
    }

    public function setStatusBarBlue()
    {
        AppFacade::setStatusBar('#3B82F6', 'light', true);
    }

    public function resetStatusBar()
    {
        AppFacade::setStatusBar(null, 'auto', true);
    }

    public function render()
    {
        return <<<HTML
        <div style="padding: 20px;">
            <h2>Native Features Demo</h2>
            <p>Explore the native capabilities of the Fuse Mobile Engine.</p>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                <a href="/fuse/native/dialogs" fuse:naviga style="padding: 15px; background: #f0f0f0; border-radius: 8px; text-decoration: none; color: #333; display: flex; align-items: center;">
                    <span style="font-size: 24px; margin-right: 15px;">💬</span>
                    <div>
                        <strong>Dialogs & Toasts</strong>
                        <div style="font-size: 12px; color: #666;">Alerts, Confirmations, Snackbars</div>
                    </div>
                </a>

                <a href="/fuse/native/device" fuse:naviga style="padding: 15px; background: #f0f0f0; border-radius: 8px; text-decoration: none; color: #333; display: flex; align-items: center;">
                    <span style="font-size: 24px; margin-right: 15px;">📱</span>
                    <div>
                        <strong>Device Features</strong>
                        <div style="font-size: 12px; color: #666;">Vibration, Flashlight, Battery, ID</div>
                    </div>
                </a>
                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px;">
                    <a href="/fuse/native/device/vibrate" fuse:naviga style="padding: 12px; background: #f9fafb; border-radius: 8px; text-decoration: none; color: #333;">
                        Vibrate
                    </a>
                    <a href="/fuse/native/device/flashlight" fuse:naviga style="padding: 12px; background: #f9fafb; border-radius: 8px; text-decoration: none; color: #333;">
                        Flashlight
                    </a>
                    <a href="/fuse/native/device/battery" fuse:naviga style="padding: 12px; background: #f9fafb; border-radius: 8px; text-decoration: none; color: #333;">
                        Battery
                    </a>
                    <a href="/fuse/native/device/info" fuse:naviga style="padding: 12px; background: #f9fafb; border-radius: 8px; text-decoration: none; color: #333;">
                        Device Info
                    </a>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <h3>Status Bar</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button fuse:click="setStatusBarRed" style="padding: 10px; background: #EF4444; color: white; border: none; border-radius: 6px;">Red</button>
                    <button fuse:click="setStatusBarBlue" style="padding: 10px; background: #3B82F6; color: white; border: none; border-radius: 6px;">Blue</button>
                    <button fuse:click="resetStatusBar" style="padding: 10px; background: #6B7280; color: white; border: none; border-radius: 6px;">Reset (Transparent)</button>
                </div>
            </div>

            <div style="margin-top: 30px; text-align:center;">
                <a href="/fuse/" fuse:naviga style="color: {$this->randomColor};">&larr; Back to Home</a>
            </div>
        </div>
HTML;
    }
}
