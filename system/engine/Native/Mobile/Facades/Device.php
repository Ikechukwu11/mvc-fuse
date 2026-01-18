<?php

namespace Native\Mobile\Facades;

use Native\Mobile\Native;

class Device
{
    public static function vibrate(int $duration = 100): void
    {
        Native::call('Device.Vibrate', ['duration' => $duration]);
    }

    public static function toggleFlashlight(): void
    {
        Native::call('Device.ToggleFlashlight');
    }

    /**
     * Request Device ID.
     * Result will be dispatched via 'Device.IdReceived' event (example).
     */
    public static function getId(): void
    {
        Native::call('Device.GetId');
    }

    public static function getInfo(): void
    {
        Native::call('Device.GetInfo');
    }

    public static function getBatteryInfo(): void
    {
        Native::call('Device.GetBatteryInfo');
    }
}
