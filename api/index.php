<?php
// api/index.php

// Set response headers
header("Content-Type: application/json; charset=UTF-8");

// Include required files
require_once __DIR__ . '/config/Environment.php';

// Initialize environment configuration
Environment::initialize();

// Include configuration and routing
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/routes.php';

// Initialize routes
Route::initialize();

// Get request information
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Create and dispatch router
$dispatcher = new RouteDispatcher();
$dispatcher->dispatch($method, $uri);
