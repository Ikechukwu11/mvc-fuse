<?php
namespace App\Fuse;

use Engine\Fuse\Component;
use App\Models\User;

class Register extends Component
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
            // Clear fields
            $this->name = '';
            $this->email = '';
            $this->password = '';
        } else {
            $this->message = 'Registration failed.';
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
        <div style="max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2>Register (Fuse)</h2>
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
