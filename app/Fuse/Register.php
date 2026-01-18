<?php

namespace App\Fuse;

use App\Models\User;
use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\Dialog;

class Register extends BaseFuse
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $message = '';
    public string $messageType = 'error';

    public function register()
    {
        if (empty($this->name) || empty($this->email) || empty($this->password)) {
            $this->message = 'All fields are required.';
            return;
        }

        if (User::findByEmail($this->email)) {
            $this->message = 'Email already exists.';
            return;
        }

        if (User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password
        ])) {
            // In a full SPA we might redirect using JS, but here we can just show success or redirect via header?
            // Fuse updates DOM. To redirect, we might need a client-side trigger or just show a link.
            // For now, let's show success message.
            $this->message = 'Registration successful! Please login.';
            $this->messageType = 'success';

            // Dispatch native event
            Dialog::toast('Registration successful!');
            Device::vibrate();

            // Clear fields
            $this->name = '';
            $this->email = '';
            $this->password = '';

            // Redirect after delay or let user click login?
            // Let's use Fuse redirect (client-side)
            $this->redirectTo = '/fuse/login';
        } else {
            $this->message = 'Registration failed.';
        }
    }

    public function help()
    {
        Dialog::alert('Need Help?', 'Contact our support team for assistance.', ['Call Support', 'Cancel'])
            ->event('App\Events\HelpButtonPressed')
            ->show();
    }

    public function onHelpAction($detail)
    {
        // $detail contains { index, label, id }
        if (isset($detail['label']) && $detail['label'] === 'Call Support') {
            Dialog::toast('Calling support...');
            // Future: Device::openUrl('tel:...');
        }
    }

    public function render()
    {
        $messageHtml = '';
        if ($this->message) {
            $color = $this->messageType === 'error' ? 'red' : 'green';
            $messageHtml = "<div style='color: {$color}; margin-bottom: 10px; padding: 10px; border: 1px solid {$color};'>{$this->message}</div>";
        }

        return <<<HTML
        <div
            style="max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;"
            fuse:window-on="App\Events\HelpButtonPressed:onHelpAction('\$event')"
        >
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Register (Fuse)</h2>
                <button fuse:click="help" style="background: transparent; border: 1px solid #ddd; padding: 5px 10px; cursor: pointer;">?</button>
            </div>
            {$messageHtml}

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Name</label>
                <input type="text" fuse:model="name" style="width: 100%; padding: 8px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Email</label>
                <input type="email" fuse:model="email" style="width: 100%; padding: 8px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Password</label>
                <input type="password" fuse:model="password" style="width: 100%; padding: 8px;">
            </div>
            <button fuse:click="register" style="width: 100%; background: #4F46E5; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;">Register</button>
            <p style="margin-top: 15px; text-align: center;">
                Already have an account? <a href="/fuse/login">Login</a>
            </p>
        </div>
HTML;
    }
}
