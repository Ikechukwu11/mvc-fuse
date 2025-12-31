<?php

namespace App\Controllers\Auth;

use Engine\Http\Request;

class LoginController
{
    public function showLoginForm()
    {
        return view('auth/login', ['title' => 'Login'], 'layouts/main');
    }

    public function login(Request $request)
    {
        $data = $request->input();
        $user = \App\Models\User::findByEmail($data['email'] ?? '');

        if ($user && password_verify($data['password'] ?? '', $user->password)) {
            session_put('user_id', $user->id);
            session_put('user_role', 'user'); // Default role
            flash('success', 'Welcome back, ' . $user->name . '!');
            return redirect('/dashboard');
        }

        flash('error', 'Invalid credentials.');
        return redirect('/login');
    }

    public function logout()
    {
        session_forget('user_id');
        session_forget('user_role');
        flash('success', 'Logged out successfully.');
        return redirect('/login');
    }
}
