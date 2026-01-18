<?php

namespace Native\Mobile\Facades;

use Native\Mobile\Native;
use Native\Mobile\PendingAlert;

class Dialog
{
    public static function alert(string $title, string $message, array $buttons = []): PendingAlert
    {
        return new PendingAlert($title, $message, $buttons);
    }

    /**
     * Show a confirmation dialog with two buttons.
     *
     * @param string $title
     * @param string $message
     * @param string $confirmLabel
     * @param string $cancelLabel
     * @return PendingAlert
     */
    public static function confirm(string $title, string $message, string $confirmLabel = 'OK', string $cancelLabel = 'Cancel'): PendingAlert
    {
        return new PendingAlert($title, $message, [$confirmLabel, $cancelLabel]);
    }

    public static function toast(string $message, string $duration = 'long'): void
    {
        Native::call('Dialog.Toast', [
            'message' => $message,
            'duration' => $duration,
        ]);
    }
}
