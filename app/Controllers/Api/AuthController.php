<?php

namespace App\Controllers\Api;

use App\Models\User;
use Engine\Http\Request;

class AuthController
{
    private function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        return $data;
    }

    public function register(Request $request)
    {
        $data = $request->input();
        
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return $this->jsonResponse(['error' => 'All fields are required'], 400);
        }

        if (User::findByEmail($data['email'])) {
            return $this->jsonResponse(['error' => 'Email already exists'], 409);
        }

        if (User::create($data)) {
            return $this->jsonResponse(['message' => 'Registration successful'], 201);
        }

        return $this->jsonResponse(['error' => 'Registration failed'], 500);
    }

    public function login(Request $request)
    {
        $data = $request->input();

        if (empty($data['email']) || empty($data['password'])) {
            return $this->jsonResponse(['error' => 'Email and password are required'], 400);
        }

        $user = User::findByEmail($data['email']);

        if ($user && password_verify($data['password'], $user['password'])) {
            // For API, ideally return a token. But we are using session for now.
            // If the client is a browser (SPA), session works.
            // If it's a mobile app, we'd need JWT/Token.
            // Let's assume session for consistency with the rest of the app.
            session_set('user_id', $user['id']);
            return $this->jsonResponse([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email']
                ]
            ]);
        }

        return $this->jsonResponse(['error' => 'Invalid credentials'], 401);
    }

    public function logout()
    {
        session_remove('user_id');
        return $this->jsonResponse(['message' => 'Logged out successfully']);
    }
}
