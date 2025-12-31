<?php
$basePath = env('APP_BASE_PATH', '');

if (isset($_SERVER['REQUEST_URI']) && $basePath !== '' && $basePath !== '/' && !str_starts_with($_SERVER['REQUEST_URI'], $basePath)) {
    $basePath = '';
}
define("BASE_PATH", "$basePath");
return [
    'name' => env('APP_NAME', 'MVC'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'base_path' => $basePath,
    'timezone' => env('APP_TIMEZONE', 'UTC'),
];
