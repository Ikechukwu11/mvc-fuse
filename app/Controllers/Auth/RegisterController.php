<?php

namespace App\Controllers\Auth;

use App\Models\User;
use Engine\Http\Request;

class RegisterController
{
    public function showRegistrationForm()
    {
        return view('auth/register', ['title' => 'Register'], 'layouts/main');
    }

    public function register(Request $request)
    {
        $data = $request->input();
        // Basic Validation
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            flash('error', 'All fields are required.');
            return redirect('/register');
        }

        if (User::findByEmail($data['email'])) {
            flash('error', 'Email already exists.');
            return redirect('/register');
        }

        try {
            User::create($data);
            flash('success', 'Registration successful! Please login.');
            return redirect('/login');
        } catch (\Exception $e) {
            flash('error', 'Registration failed.');
            return redirect('/register');
        }
    }
}
