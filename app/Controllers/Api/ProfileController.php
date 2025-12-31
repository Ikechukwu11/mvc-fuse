<?php

namespace App\Controllers\Api;

use App\Models\User;
use Engine\Http\Request;
use Engine\Storage\Storage;

class ProfileController
{
    private function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        return $data;
    }

    private function getUserId()
    {
        // For API, we should ideally use token auth, but for now we'll rely on session if cookies are shared,
        // or just return 401 if not logged in.
        return session_get('user_id');
    }

    public function index()
    {
        $id = $this->getUserId();
        if (!$id) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $user = User::find($id);
        if (!$user) {
            return $this->jsonResponse(['error' => 'User not found'], 404);
        }

        $bio = Storage::get("bios/{$id}.txt");

        return $this->jsonResponse([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'created_at' => $user['created_at']
            ],
            'bio' => $bio
        ]);
    }

    public function update(Request $request)
    {
        $id = $this->getUserId();
        if (!$id) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = $request->input();
        
        if (empty($data['name']) || empty($data['email'])) {
            return $this->jsonResponse(['error' => 'Name and email are required'], 400);
        }

        // Basic check if email is taken by another user
        $existing = User::findByEmail($data['email']);
        if ($existing && $existing['id'] != $id) {
            return $this->jsonResponse(['error' => 'Email already in use'], 409);
        }

        User::update($id, [
            'name' => $data['name'],
            'email' => $data['email']
        ]);

        return $this->jsonResponse(['message' => 'Profile updated successfully']);
    }

    public function updateBio(Request $request)
    {
        $id = $this->getUserId();
        if (!$id) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $bio = $request->input('bio');
        if ($bio === null) {
            return $this->jsonResponse(['error' => 'Bio content is required'], 400);
        }

        Storage::put("bios/{$id}.txt", $bio);

        return $this->jsonResponse(['message' => 'Bio updated successfully']);
    }

    public function deleteBio()
    {
        $id = $this->getUserId();
        if (!$id) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        Storage::delete("bios/{$id}.txt");

        return $this->jsonResponse(['message' => 'Bio deleted successfully']);
    }
}
