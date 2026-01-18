<?php

namespace Native\Mobile\Facades;

use Native\Mobile\Native;

class Secure
{
    public static function set(string $key, string $value): void
    {
        Native::call('Secure.Set', ['key' => $key, 'value' => $value]);
    }

    public static function get(string $key): void
    {
        Native::call('Secure.Get', ['key' => $key]);
    }

    public static function delete(string $key): void
    {
        Native::call('Secure.Delete', ['key' => $key]);
    }
}

