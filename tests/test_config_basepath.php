<?php
// Mock env helper if not loaded (but we will load bootstrap)
// Actually we can just require the config file directly after setting up env mock or loading bootstrap.

require __DIR__ . '/../system/engine/Bootstrap.php';
\Engine\Bootstrap::init();

// Mock Env class or .env loading is done by Bootstrap.
// .env has APP_BASE_PATH=/php/mvc

// Test Case 1: Mismatch (Simulate php -S root)
$_SERVER['REQUEST_URI'] = '/foo';
$config = require __DIR__ . '/../config/app.php';
echo "URI: /foo, Config BasePath: '" . $config['base_path'] . "'\n";

// Test Case 2: Match (Simulate Apache subdir)
$_SERVER['REQUEST_URI'] = '/php/mvc/foo';
$config = require __DIR__ . '/../config/app.php';
echo "URI: /php/mvc/foo, Config BasePath: '" . $config['base_path'] . "'\n";

// Test Case 3: Empty (Simulate no config or root)
// We can't easily change .env here without reloading Env, but we can rely on the logic we just added.
// The logic depends on env('APP_BASE_PATH') returning /php/mvc.

// Test Case 4: Root Base Path (Simulate APP_BASE_PATH=/)
// This is harder to test without mocking Env::get completely.
// But we can verify the logic structure.
