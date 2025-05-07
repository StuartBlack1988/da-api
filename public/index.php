<?php

require_once __DIR__ . '/../src/Auth/routes.php';
require_once __DIR__ . '/../src/User/routes.php';
require_once __DIR__ . '/../src/ApiAuth/routes.php';

// Initialize API auth middleware
$apiAuthMiddleware = new \App\ApiAuth\ApiAuthMiddleware($db);

// Add middleware to router - this must be done BEFORE any routes are defined
$router->before('GET|POST|PUT|DELETE', '/.*', function() use ($apiAuthMiddleware) {
    return $apiAuthMiddleware->handle($request);
});

// Include route files
try {
    error_log("Loading route files...");
    require_once __DIR__ . '/src/Auth/routes.php';
    require_once __DIR__ . '/src/User/routes.php';
    require_once __DIR__ . '/src/ApiAuth/routes.php';
    error_log("Route files loaded successfully");
} catch (Exception $e) {
    error_log("Error loading route files: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    throw $e;
}

// Run the router
$router->run(); 