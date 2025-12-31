<?php

// API Routes
// All routes here are prefixed with /api automatically

use App\Controllers\Api\ProfileController;
use App\Controllers\Api\AuthController;

$router->put('/test', function() {
    return ['status' => 'success', 'method' => 'PUT'];
});

$router->delete('/test', function() {
    return ['status' => 'success', 'method' => 'DELETE'];
});

// Profile API Routes
$router->get('/profile', [ProfileController::class, 'index']);
$router->put('/profile', [ProfileController::class, 'update']);
$router->put('/profile/bio', [ProfileController::class, 'updateBio']);
$router->delete('/profile/bio', [ProfileController::class, 'deleteBio']);
