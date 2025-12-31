<?php

namespace App\Fuse;

use Engine\Fuse\Component;
use App\Models\User;
use Engine\Storage\Storage;

class Profile extends Component
{
    public string $name = '';
    public string $email = '';
    public string $bio = '';

    public string $message = '';
    public string $messageType = 'success'; // success or error

    public function mount()
    {
        $id = session_get('user_id');
        if (!$id) return;

        $user = User::find($id);
        if ($user) {
            $user = $user->toArray();
            $this->name = $user['name'];
            $this->email = $user['email'];
        }

        $this->bio = Storage::get("bios/{$id}.txt") ?? '';
    }

    public function update()
    {
        $id = session_get('user_id');
        if (!$id) return;

        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email'
        ]);

        $user = User::find($id);
        if ($user) {
            $user->update([
                'name' => $this->name,
                'email' => $this->email
            ]);
        }

        $this->message = 'Profile updated successfully.';
        $this->messageType = 'success';

        $this->dispatch('profile-updated', ['name' => $this->name]);
    }

    public function updateBio()
    {
        $id = session_get('user_id');
        if (!$id) return;

        Storage::put("bios/{$id}.txt", $this->bio);

        $this->message = 'Bio updated successfully.';
        $this->messageType = 'success';
    }

    public function deleteBio()
    {
        $id = session_get('user_id');
        if (!$id) return;

        Storage::delete("bios/{$id}.txt");
        $this->bio = '';

        $this->message = 'Bio deleted successfully.';
        $this->messageType = 'success';
    }

    public function render()
    {
        $messageHtml = '';
        if ($this->message) {
            $color = $this->messageType === 'error' ? 'red' : 'green';
            $messageHtml = "<div style='color: {$color}; margin-bottom: 10px; padding: 10px; border: 1px solid {$color};'>{$this->message}</div>";
        }

        $nameError = $this->getError('name') ? "<div style='color: red; font-size: 0.9em;'>{$this->getError('name')}</div>" : '';
        $emailError = $this->getError('email') ? "<div style='color: red; font-size: 0.9em;'>{$this->getError('email')}</div>" : '';

        return <<<HTML
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2>Profile (Fuse Component)</h2>
            {$messageHtml}
            
            <div style="background: #f3f4f6; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
                <strong>Computed Name:</strong> {$this->decoratedName}
            </div>

            <form fuse:submit="update" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 20px; border-radius: 4px;">
                <h3>Personal Info</h3>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Name</label>
                    <input type="text" fuse:model="name" style="width: 100%; padding: 8px;">
                    {$nameError}
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Email</label>
                    <input type="email" fuse:model="email" style="width: 100%; padding: 8px;">
                    {$emailError}
                </div>
                <button type="submit" style="background: #4F46E5; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Update Profile</button>
            </form>

            <div style="margin-bottom: 20px; border: 1px solid #ddd; padding: 20px; border-radius: 4px;">
                <h3>Bio</h3>
                <div style="margin-bottom: 15px;">
                    <textarea fuse:model="bio" rows="5" style="width: 100%; padding: 8px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button fuse:click="updateBio" style="background: #10B981; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Save Bio</button>
                    <button fuse:click="deleteBio" style="background: #EF4444; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Delete Bio</button>
                </div>
            </div>
        </div>
HTML;
    }
}
