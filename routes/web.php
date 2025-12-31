<?php

use App\Controllers\HomeController;
use App\Controllers\DemoController;
use App\Controllers\Auth\LoginController;
use Engine\Http\Middleware\AuthMiddleware;
use App\Middleware\IsAdmin;
use Engine\Fuse\Manager;
use Engine\Http\Request;
use App\Fuse\Docs;

use App\Controllers\Auth\RegisterController;
use App\Controllers\ProfileController;

$router->get('/', [HomeController::class, 'index']);

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
    return view('fuse_test', [], 'layouts/main');
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
    return \App\Fuse\Docs::renderPage(['title' => 'Fuse Docs', 'current' => $current]);
});
