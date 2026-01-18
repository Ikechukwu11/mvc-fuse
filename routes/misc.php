<?php

use App\Fuse\Docs;
use App\Fuse\Home;
use Engine\Fuse\Manager;
use Engine\Http\Request;
use Engine\Http\Response;
use App\Middleware\IsAdmin;
use App\Controllers\DemoController;
use App\Controllers\ProfileController;

use App\Controllers\Auth\LoginController;
use Engine\Http\Middleware\AuthMiddleware;
use App\Controllers\Auth\RegisterController;

$router->get('/', [Home::class, 'render'])->name('home');
$router->get('/phpinfo', [Home::class, 'render'])->name('info');

// Auth Routes
$router->get('/register', [RegisterController::class, 'showRegistrationForm']);
$router->post('/register', [RegisterController::class, 'register']);
$router->get('/login', [LoginController::class, 'showLoginForm']);
$router->post('/login', [LoginController::class, 'login']);
$router->get('/logout', [LoginController::class, 'logout']);

// Profile Routes (Protected)
$router->get('/profile', [ProfileController::class, 'index'], [new AuthMiddleware()]);
$router->put('/profile', [ProfileController::class, 'update'], [new AuthMiddleware()]);
$router->put('/profile/bio', [ProfileController::class, 'updateBio'], [new AuthMiddleware()]);
$router->delete('/profile/bio', [ProfileController::class, 'deleteBio'], [new AuthMiddleware()]);

// Protected Routes
$router->get('/secret', [DemoController::class, 'secret'], [new AuthMiddleware()]);

// Dashboard (Protected)
$router->get('/dashboard', function () {
  return view('home', ['title' => 'Dashboard', 'message' => 'You are logged in!'], 'layouts/main');
}, [new AuthMiddleware()]);

// Admin Only
$router->get('/admin', function () {
  return "<h1>Admin Area</h1>";
}, [new AuthMiddleware(), new IsAdmin()]);

// Fuse Route
$router->post('/fuse/update', function (Request $request) {
  $manager = new Manager();
  return $manager->handleRequest($request);
});

// Fuse Test
$router->get('/fuse-test', function () {
  return view('fuse_test', [], '');
});

// Full Page Fuse Component Example
$router->get('/todo-app', function () {
  return \App\Fuse\TodoList::renderPage();
});

// Fuse Profile
$router->get('/fuse/profile', function () {
  return \App\Fuse\Profile::renderPage();
}, [new AuthMiddleware()]);

// Fuse Auth
$router->get('/fuse/register', function () {
  return \App\Fuse\Register::renderPage();
});

$router->get('/fuse/login', function () {
  return \App\Fuse\Login::renderPage();
});

// Fuse Docs
$router->get('/docs', function (Request $request) {
  $current = $request->input('section') ?? 'introduction';
  return Docs::renderPage(['title' => 'Fuse Docs', 'current' => $current]);
});

// Fuse Native Demo
$router->get('/music', function () {
  return \App\Fuse\MusicPlayer::renderPage();
});

$router->get('/music/albumart/default', function () {
  $res = new Response();
  $root = dirname(__DIR__);

  $localPath = storage_path('app/music/default_albumart.svg');
  $mobilePath = dirname($root) . '/persisted_data/storage/app/music/default_albumart.svg';

  $path = null;
  if (file_exists($mobilePath)) {
    $path = $mobilePath;
  } elseif (file_exists($localPath)) {
    $path = $localPath;
  }

  $body = $path ? (file_get_contents($path) ?: '') : '';
  if ($body === '') {
    $body = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300" viewBox="0 0 300 300"><rect width="300" height="300" fill="#111827"/><text x="150" y="160" text-anchor="middle" font-family="Arial" font-size="20" fill="#9ca3af">No Cover</text></svg>';
  }

  return $res
    ->header('Cache-Control', 'public, max-age=86400')
    ->raw($body, 'image/svg+xml; charset=utf-8', 200);
});

$router->get('/music/albumart/{id}', function ($id) {
  $res = new Response();
  $root = dirname(__DIR__);

  $safeId = preg_match('/^\d+$/', (string)$id) ? (string)$id : null;
  if ($safeId === null) {
    return $res->setStatus(302)->header('Location', '/music/albumart/default')->raw('', 'text/plain; charset=utf-8', 302);
  }

  $localPath = storage_path('app/music/art/' . $safeId . '.jpg');
  $mobilePath = dirname($root) . '/persisted_data/storage/app/music/art/' . $safeId . '.jpg';

  $path = null;
  if (file_exists($mobilePath)) {
    $path = $mobilePath;
  } elseif (file_exists($localPath)) {
    $path = $localPath;
  }

  if (!$path) {
    return $res->setStatus(302)->header('Location', '/music/albumart/default')->raw('', 'text/plain; charset=utf-8', 302);
  }

  $body = file_get_contents($path);
  if ($body === false) {
    return $res->setStatus(302)->header('Location', '/music/albumart/default')->raw('', 'text/plain; charset=utf-8', 302);
  }

  return $res
    ->header('Cache-Control', 'public, max-age=86400')
    ->raw($body, 'image/jpeg', 200);
});

// Native event ingestion endpoint for WebView bridge
$router->post('/_native/api/events', function (Request $request) {
  return ['status' => 'ok'];
});

$router->get('/test-status-bar', function () {
  return view('home', ['title' => 'Status Bar Test', 'message' => 'Status Bar Should be Blue'], 'layouts/main')
    ->withStatusBar('#3B82F6', 'auto', true);
});

$router->get('/fuse/native', function () {
  return \App\Fuse\NativeDemo\Index::renderPage();
});

$router->get('/fuse/native/dialogs', function () {
  return \App\Fuse\NativeDemo\Dialogs::renderPage();
});

$router->get('/fuse/native/device', function () {
  return \App\Fuse\NativeDemo\Device::renderPage();
});

// Split device pages
$router->get('/fuse/native/device/vibrate', function () {
  return \App\Fuse\NativeDemo\DeviceVibrate::renderPage();
});

$router->get('/fuse/native/device/flashlight', function () {
  return \App\Fuse\NativeDemo\DeviceFlashlight::renderPage();
});
$router->get('/fuse/native/device/battery', function () {
  return \App\Fuse\NativeDemo\DeviceBattery::renderPage();
});
$router->get('/fuse/native/device/info', function () {
  return \App\Fuse\NativeDemo\DeviceInfo::renderPage();
});
