<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Device as DeviceFacade;

/**
 * Flashlight demo page for Fuse Native
 */
class DeviceFlashlight extends BaseFuse
{
    /** @var string */
    public string $flashlightState = 'Off';
    /** @var bool */
    public bool $flashlightLoading = false;
    /** @var string */
    public string $flashlightResult = '';

    /** Toggle flashlight */
    public function toggleFlashlight(): void
    {
        $this->flashlightLoading = true;
        $this->flashlightResult = 'Processing...';
        DeviceFacade::toggleFlashlight();
    }

    /** Handle native toggle event */
    public function onFlashlightToggled($detail): void
    {
        $state = (bool)($detail['state'] ?? false);
        $this->flashlightState = $state ? 'On' : 'Off';
        $this->flashlightLoading = false;
        $this->flashlightResult = 'Flashlight ' . ($state ? 'Enabled' : 'Disabled');
    }

    /** Handle permission request event */
    public function onFlashlightPermissionRequested($detail): void
    {
        $this->flashlightState = 'Permission Requested';
        $this->flashlightLoading = false;
        $this->flashlightResult = 'Camera permission requested';
    }

    /** Handle flashlight error */
    public function onFlashlightError($detail): void
    {
        $this->flashlightLoading = false;
        $this->flashlightResult = 'Error: ' . ($detail['error'] ?? 'Unknown');
    }

    /** Render page */
    public function render(): string
    {
        $display = $this->flashlightLoading ? 'inline-block' : 'none';
        return <<<HTML
        <div style="padding: 20px;"
            fuse:window-on="Device.FlashlightToggled:onFlashlightToggled('\$event'); Device.FlashlightPermissionRequested:onFlashlightPermissionRequested('\$event'); Device.FlashlightError:onFlashlightError('\$event')"
        >
            <h2>Flashlight</h2>
            <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                <button fuse:click="toggleFlashlight" style="padding: 12px; background: #ea580c; color: white; border: none; border-radius: 6px;">Toggle Flashlight</button>
                <span style="font-size: 12px; color: #666;">State: {$this->flashlightState}</span>
            </div>
            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 12px; color: #666;">{$this->flashlightResult}</span>
                <span style="display: {$display}; width: 14px; height: 14px; border: 2px solid #ddd; border-top-color: #ea580c; border-radius: 50%; animation: spin 0.8s linear infinite;"></span>
            </div>
            <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
            <div style="margin-top: 30px;">
                <a href="/fuse/native" fuse:naviga style="color: #4F46E5;">&larr; Back to Demo Menu</a>
            </div>
        </div>
HTML;
    }
}
