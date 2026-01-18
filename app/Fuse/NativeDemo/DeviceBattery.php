<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Device as DeviceFacade;

/**
 * Battery info demo page for Fuse Native
 */
class DeviceBattery extends BaseFuse
{
    /** @var string */
    public string $batteryInfo = 'Loading...';

    /** Request battery info */
    public function mount(): void
    {
        DeviceFacade::getBatteryInfo();
    }

    /** Handle battery info event */
    public function onBatteryInfo($detail): void
    {
        $level = $detail['level'] ?? '?';
        $charging = !empty($detail['isCharging']) ? 'Yes' : 'No';
        $this->batteryInfo = "Level: {$level}%, Charging: {$charging}";
    }

    /** Re-request battery info */
    public function refresh(): void
    {
        DeviceFacade::getBatteryInfo();
    }

    /** Render page */
    public function render(): string
    {
        return <<<HTML
        <div style="padding: 20px;"
            fuse:window-on="Device.BatteryInfo:onBatteryInfo('\$event')"
        >
            <h2>Battery</h2>
            <div style="background: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; margin-top: 10px;">
                {$this->batteryInfo}
            </div>
            <button fuse:click="refresh" style="margin-top: 10px; font-size: 12px;">Refresh</button>
            <div style="margin-top: 30px;">
                <a href="/fuse/native" fuse:naviga style="color: #4F46E5;">&larr; Back to Demo Menu</a>
            </div>
        </div>
HTML;
    }
}
