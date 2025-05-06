<?php

require_once __DIR__ . '/../src/Auth/routes.php';
require_once __DIR__ . '/../src/User/routes.php';
require_once __DIR__ . '/../src/ApiAuth/routes.php';

// Initialize API auth middleware
$apiAuthMiddleware = new \App\ApiAuth\ApiAuthMiddleware($db);

// Add middleware to router
$router->before('GET|POST|PUT|DELETE', '/.*', function() use ($apiAuthMiddleware) {
    return $apiAuthMiddleware->handle($request);
});

// Run the router
$router->run(); 