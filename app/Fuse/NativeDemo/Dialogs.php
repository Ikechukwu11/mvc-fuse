<?php

namespace App\Fuse\NativeDemo;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Dialog;

class Dialogs extends BaseFuse
{
    public string $lastAction = 'None';

    public function showToast()
    {
        Dialog::toast('This is a native toast with icon!');
        $this->lastAction = 'Toast Shown';
    }

    public function showLongToast()
    {
        Dialog::toast('This is a longer toast message...', 'long');
        $this->lastAction = 'Long Toast Shown';
    }

    public function showAlert()
    {
        Dialog::alert('Native Alert', 'This is a native alert dialog with the app icon.', ['Nice!'])
            ->event('App\Fuse\NativeDemo\Events\AlertClosed')
            ->show();
    }

    public function showConfirm()
    {
        Dialog::confirm('Confirm Action', 'Are you sure you want to proceed?', 'Yes', 'No')
            ->event('App\Fuse\NativeDemo\Events\ConfirmAction')
            ->show();
    }

    public function onAlertClosed($detail)
    {
        $this->lastAction = 'Alert Closed: ' . ($detail['label'] ?? 'Unknown');
    }

    public function onConfirmAction($detail)
    {
        $this->lastAction = 'Confirm Result: ' . ($detail['label'] ?? 'Unknown');
    }

    public function render()
    {
        return <<<HTML
        <div style="padding: 20px;"
             fuse:window-on="App\Fuse\NativeDemo\Events\AlertClosed:onAlertClosed('\$event')"
             fuse:window-on="App\Fuse\NativeDemo\Events\ConfirmAction:onConfirmAction('\$event')"
        >
            <h2>Dialogs & Toasts</h2>

            <div style="background: #eef2ff; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                Last Action: <strong>{$this->lastAction}</strong>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button fuse:click="showToast" style="padding: 12px; background: #4F46E5; color: white; border: none; border-radius: 6px;">Show Toast</button>
                <button fuse:click="showLongToast" style="padding: 12px; background: #4338ca; color: white; border: none; border-radius: 6px;">Show Long Toast</button>
                <button fuse:click="showAlert" style="padding: 12px; background: #059669; color: white; border: none; border-radius: 6px;">Show Alert</button>
                <button fuse:click="showConfirm" style="padding: 12px; background: #d97706; color: white; border: none; border-radius: 6px;">Show Confirmation</button>
            </div>

            <div style="margin-top: 30px;">
                <a href="/fuse/native" fuse:naviga style="color: #4F46E5;">&larr; Back to Demo Menu</a>
            </div>
        </div>
HTML;
    }
}
