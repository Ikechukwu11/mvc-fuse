<?php

namespace Native\Mobile\Facades;

use Native\Mobile\Native;

class MediaLibrary
{
    public static function scan(): void
    {
        Native::call('MediaLibrary.Scan');
    }

    public static function search(string $query): void
    {
        Native::call('MediaLibrary.Search', ['query' => $query]);
    }
}

