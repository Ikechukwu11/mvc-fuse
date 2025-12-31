<?php

namespace App\Controllers;

use App\Models\User;
use Engine\Http\Request;
use Engine\Storage\Storage;

class ProfileController
{
    public function index()
    {
        $id = session_get('user_id');
        if (!$id) {
            return redirect('/login');
        }

        $user = User::find($id);
        var_dump($user);
        if (!$user) {
            session_forget('user_id');
            return redirect('/login');
        }

        $bio = Storage::get("bios/{$id}.txt");

        return view('profile/index', compact('user', 'bio'), 'layouts/main');
    }

    public function update(Request $request)
    {
        $id = session_get('user_id');
        if (!$id) {
            return redirect('/login');
        }

        $data = $request->input();

        // Validation could go here

        $user = User::find($id);
        if ($user) {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->updated_at = date('Y-m-d H:i:s');
            $user->save();
        }

        flash('success', 'Profile updated successfully.');
        return redirect('/profile');
    }

    public function updateBio(Request $request)
    {
        $id = session_get('user_id');
        if (!$id) {
            return redirect('/login');
        }

        $bio = $request->input('bio');
        Storage::put("bios/{$id}.txt", $bio);

        flash('success', 'Bio updated successfully.');
        return redirect('/profile');
    }

    public function deleteBio()
    {
        $id = session_get('user_id');
        if (!$id) {
            return redirect('/login');
        }

        Storage::delete("bios/{$id}.txt");

        flash('success', 'Bio deleted successfully.');
        return redirect('/profile');
    }
}
