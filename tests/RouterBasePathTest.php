<?php
require_once __DIR__ . '/../system/engine/Config.php';
require_once __DIR__ . '/../system/engine/Router.php';

use Engine\Config;
use Engine\Router;

// Setup Config
Config::load([
    'app' => [
        'base_path' => '/php/mvc'
    ]
]);

$router = new Router();
$router->get('/test', function() {
    return 'matched';
});

// Test matching
$uri = '/php/mvc/test';
$route = $router->match('GET', $uri);

if ($route) {
    echo "Matched successfully!\n";
    print_r($route);
} else {
    echo "Failed to match.\n";
}

// Test without base path (should fail or match if configured differently?)
// With base path set, /test should NOT match if URI is /test (unless we handle that logic, but usually it comes in as /php/mvc/test)
$uri2 = '/test'; 
$route2 = $router->match('GET', $uri2);
if ($route2) {
    echo "Matched /test incorrectly (should expect base path).\n";
} else {
    echo "Correctly failed to match /test without base path.\n";
}
