<?php
require __DIR__ . '/system/engine/Bootstrap.php';
\Engine\Bootstrap::init();

$class = 'App\Fuse\Counter';
if (class_exists($class)) {
    echo "Class $class found!\n";
    $c = new $class();
    echo "Instance created.\n";
} else {
    echo "Class $class NOT found.\n";
    // Debug autoloader
    $loader = new \Engine\Autoloader(); // This won't have the prefixes unless we inspect the global one or re-add
    // But Bootstrap::init() registers the loader.
    // Let's manually check file path logic
    $root = __DIR__;
    $appPath = $root . '/app/';
    $relative = 'Fuse/Counter.php';
    $file = $appPath . $relative;
    echo "Checking file: $file\n";
    if (file_exists($file)) {
        echo "File exists.\n";
    } else {
        echo "File does not exist.\n";
    }
}
