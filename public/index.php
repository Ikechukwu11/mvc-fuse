<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require dirname(__DIR__) . '/system/engine/Core/Bootstrap.php';
\Engine\Core\Bootstrap::init();

use Engine\Core\Kernel;
use Engine\Http\Router;
use Engine\Http\Request;
use Engine\Core\Config;

$router = new Router();
$kernel = new Kernel($router);
$request = new Request();
$kernel->handle($request);
