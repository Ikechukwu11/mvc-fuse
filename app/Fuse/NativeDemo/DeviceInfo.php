<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Device as DeviceFacade;

/**
 * Device info demo page for Fuse Native
 */
class DeviceInfo extends BaseFuse
{
    /** @var string */
    public string $deviceInfo = 'Loading...';

    /** Request device info */
    public function mount(): void
    {
        DeviceFacade::getInfo();
    }

    /** Handle Device.Info event */
    public function onDeviceInfo($detail): void
    {
        $this->deviceInfo = json_encode($detail, JSON_PRETTY_PRINT);
    }

    /** Refresh device info */
    public function refresh(): void
    {
        DeviceFacade::getInfo();
    }

    /** Render page */
    public function render(): string
    {
        return <<<HTML
        <div style="padding: 20px;"
            fuse:window-on="Device.Info:onDeviceInfo('\$event')"
        >
            <h2>Device Info</h2>
            <pre style="background: #f3f4f6; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px; margin-top: 10px;">{$this->deviceInfo}</pre>
            <button fuse:click="refresh" style="margin-top: 10px; font-size: 12px;">Refresh</button>
            <div style="margin-top: 30px;">
                <a href="/fuse/native" fuse:naviga style="color: #4F46E5;">&larr; Back to Demo Menu</a>
            </div>
        </div>
HTML;
    }
}
