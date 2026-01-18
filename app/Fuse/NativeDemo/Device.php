<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Device as DeviceFacade;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\App as AppFacade;

class Device extends BaseFuse
{
    public string $deviceInfo = 'Loading...';
    public string $batteryInfo = 'Loading...';
    public string $flashlightState = 'Off';
    public bool $flashlightLoading = false;
    public string $flashlightResult = '';

    public function mount()
    {
        // Request info on mount (async via events)
        DeviceFacade::getInfo();
        DeviceFacade::getBatteryInfo();
        $this->useStatusBar('#9B6516  ', 'light', true);
    }

    public function vibrateShort()
    {
        DeviceFacade::vibrate(50);
    }

    public function vibrateLong()
    {
        DeviceFacade::vibrate(500);
    }

    public function toggleFlashlight()
    {
        $this->flashlightLoading = true;
        $this->flashlightResult = 'Processing...';
        DeviceFacade::toggleFlashlight();
    }

    public function setStatusBarRed()
    {
        AppFacade::setStatusBar('#EF4444', 'light');
    }

    public function setStatusBarBlue()
    {
        AppFacade::setStatusBar('#3B82F6', 'light');
    }

    public function resetStatusBar()
    {
        AppFacade::setStatusBar(null, 'auto', true);
    }

    public function onDeviceInfo($detail)
    {
        $this->deviceInfo = json_encode($detail, JSON_PRETTY_PRINT);
    }

    public function onBatteryInfo($detail)
    {
        $this->batteryInfo = "Level: " . ($detail['level'] ?? '?') . "%, Charging: " . ($detail['isCharging'] ? 'Yes' : 'No');
    }

    public function onFlashlightToggled($detail)
    {
        $this->flashlightState = ($detail['state'] ?? false) ? 'On' : 'Off';
        $this->flashlightLoading = false;
        $this->flashlightResult = 'Flashlight ' . ($detail['state'] ? 'Enabled' : 'Disabled');
    }

    public function onFlashlightPermissionRequested($detail)
    {
        $this->flashlightState = 'Permission Requested';
        $this->flashlightLoading = false;
        $this->flashlightResult = 'Camera permission requested';
    }

    public function onFlashlightError($detail)
    {
        $this->flashlightLoading = false;
        $this->flashlightResult = 'Error: ' . ($detail['error'] ?? 'Unknown');
    }

    public function render()
    {
        $display = $this->flashlightLoading ? 'inline-block' : 'none';
        return <<<HTML
        <div style="padding: 20px;"
            fuse:window-on="Device.Info:onDeviceInfo('\$event'); Device.BatteryInfo:onBatteryInfo('\$event'); Device.FlashlightToggled:onFlashlightToggled('\$event'); Device.FlashlightPermissionRequested:onFlashlightPermissionRequested('\$event'); Device.FlashlightError:onFlashlightError('\$event')"
        >
            <h2>Device Features</h2>

            <div style="margin-bottom: 20px;">
                <h3>Battery</h3>
                <div style="background: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace;">
                    {$this->batteryInfo}
                </div>
                <button fuse:click="mount" style="margin-top: 5px; font-size: 12px;">Refresh</button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button fuse:click="vibrateShort" style="padding: 12px; background: #4F46E5; color: white; border: none; border-radius: 6px;">Vibrate (Short)</button>
                <button fuse:click="vibrateLong" style="padding: 12px; background: #4338ca; color: white; border: none; border-radius: 6px;">Vibrate (Long)</button>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button fuse:click="toggleFlashlight" style="flex: 1; padding: 12px; background: #ea580c; color: white; border: none; border-radius: 6px;">Toggle Flashlight</button>
                    <span style="font-size: 12px; color: #666;">State: {$this->flashlightState}</span>
                </div>
                <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 12px; color: #666;">{$this->flashlightResult}</span>
                    <span style="display: {$display}; width: 14px; height: 14px; border: 2px solid #ddd; border-top-color: #ea580c; border-radius: 50%; animation: spin 0.8s linear infinite;"></span>
                </div>
                <style>
                    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                </style>
            </div>

            <div style="margin-top: 20px;">
                <h3>Status Bar</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button fuse:click="setStatusBarRed" style="padding: 10px; background: #EF4444; color: white; border: none; border-radius: 6px;">Red</button>
                    <button fuse:click="setStatusBarBlue" style="padding: 10px; background: #3B82F6; color: white; border: none; border-radius: 6px;">Blue</button>
                    <button fuse:click="resetStatusBar" style="padding: 10px; background: #6B7280; color: white; border: none; border-radius: 6px;">Reset (Transparent)</button>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <h3>Device Info</h3>
                <pre style="background: #f3f4f6; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px;">{$this->deviceInfo}</pre>
            </div>

            <div style="margin-top: 30px;">
                <a href="/fuse/native" fuse:naviga style="color: #4F46E5;">&larr; Back to Demo Menu</a>
            </div>
        </div>
HTML;
    }
}
