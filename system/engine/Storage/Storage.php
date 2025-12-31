<?php

namespace Engine\Storage;

class Storage
{
    /**
     * Get the absolute path to the storage folder.
     */
    public static function path(string $path = ''): string
    {
        $root = dirname(__DIR__, 3); // system/engine/Storage -> system/engine -> system -> root
        return $root . DIRECTORY_SEPARATOR . 'storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    /**
     * Check if a file exists.
     */
    public static function exists(string $path): bool
    {
        return file_exists(self::path($path));
    }

    /**
     * Get file contents.
     */
    public static function get(string $path): ?string
    {
        if (self::exists($path)) {
            return file_get_contents(self::path($path));
        }
        return null;
    }

    /**
     * Write contents to a file.
     */
    public static function put(string $path, string $content): bool
    {
        $fullPath = self::path($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($fullPath, $content) !== false;
    }

    /**
     * Delete a file.
     */
    public static function delete(string $path): bool
    {
        if (self::exists($path)) {
            return unlink(self::path($path));
        }
        return false;
    }

    /**
     * Get the public URL for a file.
     * Assumes 'storage' folder in public is linked to storage/public
     */
    public static function url(string $path): string
    {
        // Check if app.url is set
        $appUrl = rtrim(config('app.url', ''), '/');
        $basePath = rtrim(config('app.base_path', ''), '/');

        // If path starts with 'public/', strip it to map to the symlink
        // e.g. public/images/logo.png -> /storage/images/logo.png
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return $appUrl . $basePath . '/storage/' . ltrim($path, '/');
    }
}
