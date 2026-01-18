<?php

/**
 * boot_native.php
 *
 * Entry point for the MVC Mobile application.
 * This script is executed by the embedded PHP runtime on the Android device.
 * It initializes the framework and handles the request.
 */

// Define a constant to identify we are running in native mode
define('MVC_NATIVE_MODE', true);

// Adjust the root directory path since we are in system/engine/Mobile
// The framework root is 3 levels up from system/engine/Mobile
// But on Android, the file structure might be flattened or different.
// However, assuming we copy the whole project structure:
$rootDir = dirname(dirname(dirname(__DIR__)));

// Require the bootstrap
require_once $rootDir . '/system/engine/Core/Bootstrap.php';

// Initialize the framework
\Engine\Core\Bootstrap::init();

use Engine\Core\Kernel;
use Engine\Http\Router;
use Engine\Http\Request;
use Native\Mobile\Native;

// Create the kernel components
$router = new Router();
$GLOBALS['__router'] = $router;
$kernel = new Kernel($router);

// Create the request object
// The $_SERVER variables are already populated by the native C bridge
$request = new Request();
$GLOBALS['__randomColor'] = getRandomColor();
Native::call('App.SetStatusBar', [
  'color' => $GLOBALS['__randomColor'],
  'style' => 'auto',
  'overlay' => true
]);
// Handle the request
$kernel->handle($request);
