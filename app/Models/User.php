<?php

namespace App\Models;

use Engine\Database\Model;

class User extends Model
{
    protected string $table = 'users';

    /**
     * Create a new user with hashed password and timestamps.
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes): static
    {
        // Hash password if present
        if (isset($attributes['password'])) {
            $attributes['password'] = password_hash($attributes['password'], PASSWORD_BCRYPT);
        }

        // Add timestamps
        $now = date('Y-m-d H:i:s');
        if (!isset($attributes['created_at'])) {
            $attributes['created_at'] = $now;
        }
        if (!isset($attributes['updated_at'])) {
            $attributes['updated_at'] = $now;
        }

        return parent::create($attributes);
    }

    /**
     * Find a user by email.
     *
     * @param string $email
     * @return static|null
     */
    public static function findByEmail(string $email): ?static
    {
        return static::query()->where('email', '=', $email)->first();
    }
}
