<?php

namespace App\Fuse;

use Engine\Fuse\Component;
use App\Models\User;

/**
 * Login Component
 *
 * Handles user authentication via Fuse.
 */
class Login extends Component
{
    /**
     * @var string User email input
     */
    public string $email = '';

    /**
     * @var string User password input
     */
    public string $password = '';

    /**
     * @var string Feedback message for the user
     */
    public string $message = '';

    /**
     * Attempt to log the user in.
     *
     * Validates credentials against the database.
     * On success, redirects to the profile page.
     * On failure, sets an error message.
     *
     * @return void
     */
    public function login()
    {
        if (empty($this->email) || empty($this->password)) {
            $this->message = 'Email and password are required.';
            return;
        }

        $user = User::findByEmail($this->email);

        if ($user && password_verify($this->password, $user->password)) {
            session_put('user_id', $user->id);
            // Redirect using the new redirect helper
            $this->redirect('/fuse/profile');
        } else {
            $this->message = 'Invalid credentials.';
        }
    }

    /**
     * Render the login form component.
     *
     * @return string HTML output
     */
    public function render()
    {
        $messageHtml = '';
        if ($this->message) {
            $color = strpos($this->message, 'successful') !== false ? 'green' : 'red';
            $messageHtml = "<div style='color: {$color}; margin-bottom: 10px; padding: 10px; border: 1px solid {$color};'>{$this->message}</div>";
        }

        return <<<HTML
        <div style="max-width: 400px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2>Login (Fuse)</h2>
            {$messageHtml}

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Email</label>
                <input type="email" fuse:model="email" style="width: 100%; padding: 8px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Password</label>
                <input type="password" fuse:model="password" style="width: 100%; padding: 8px;">
            </div>

            <button fuse:click="login" fuse:loading-target="#login-loader" style="width: 100%; background: #4F46E5; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;">Login</button>

            <div id="login-loader" style="display: none; text-align: center; margin-top: 10px; color: #4F46E5;">
                Logging in...
            </div>

            <p style="margin-top: 15px; text-align: center;">
                Don't have an account? <a href="/fuse/register">Register</a>
            </p>
        </div>
HTML;
    }
}
