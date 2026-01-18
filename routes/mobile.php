<?php

use App\Fuse\Docs;
use App\Fuse\Home;
use Engine\Fuse\Manager;
use Engine\Http\Request;
use Engine\Http\Response;
use Engine\Http\Middleware\AuthMiddleware;

$router->get('/', [Home::class, 'render'])->name('home');$router->get('/phpinfo', [Home::class, 'render'])->name('home.info');

$router->group(['prefix' => '/fuse'], function ($router) {
  $router->get('/', [Home::class, 'render'])->name('fuse.home');
  $router->get('/phpinfo', [Home::class, 'render'])->name('fuse.info');

  // Fuse Test
  $router->get('/test', function () {
    return view('fuse.test', [], 'layouts.main');
  });

  // Full Page Fuse Component Example
  $router->get('/todo-app', function () {
    return \App\Fuse\TodoList::renderPage();
  });

  // Fuse Auth
  $router->get('/register', function () {
    return \App\Fuse\Register::renderPage();
  });

  $router->get('/login', function () {
    return \App\Fuse\Login::renderPage();
  });

  // Fuse Profile
  $router->get('/profile', function () {
    return \App\Fuse\Profile::renderPage();
  })->middleware([new AuthMiddleware()]);

  // Fuse Docs
  $router->get('/docs', function (Request $request) {
    $current = $request->input('section') ?? 'introduction';
    return Docs::renderPage(['title' => 'Fuse Docs', 'current' => $current]);
  });

  $router->post('/update', function (Request $request) {
    $manager = new Manager();
    return $manager->handleRequest($request);
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
    return view('home', ['title' => 'Status Bar Test', 'message' => 'Status Bar Should be Blue'], 'layouts/main');
  });

  $router->get('/native', function () {
    return \App\Fuse\NativeDemo\Index::renderPage();
  });

  $router->get('/native/dialogs', function () {
    return \App\Fuse\NativeDemo\Dialogs::renderPage();
  });

  $router->get('/native/device', function () {
    return \App\Fuse\NativeDemo\Device::renderPage();
  });

  // Split device pages
  $router->get('/native/device/vibrate', function () {
    return \App\Fuse\NativeDemo\DeviceVibrate::renderPage();
  });

  $router->get('/native/device/flashlight', function () {
    return \App\Fuse\NativeDemo\DeviceFlashlight::renderPage();
  });
  $router->get('/native/device/battery', function () {
    return \App\Fuse\NativeDemo\DeviceBattery::renderPage();
  });
  $router->get('/native/device/info', function () {
    return \App\Fuse\NativeDemo\DeviceInfo::renderPage();
  });
});
