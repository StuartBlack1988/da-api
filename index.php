<?php

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Verify PHP version
error_log("PHP Version: " . PHP_VERSION);

// Verify vendor directory
$vendorPath = __DIR__ . '/vendor';
$bootstrapPath = $vendorPath . '/symfony/polyfill-ctype/bootstrap.php';

error_log("Vendor path: " . $vendorPath);
error_log("Bootstrap path: " . $bootstrapPath);
error_log("File exists: " . (file_exists($bootstrapPath) ? 'yes' : 'no'));
error_log("Is readable: " . (is_readable($bootstrapPath) ? 'yes' : 'no'));

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Get the request URI and method
    $requestUri = $_SERVER['REQUEST_URI'];
    $requestMethod = $_SERVER['REQUEST_METHOD'];

    // Remove query string if present
    $requestUri = strtok($requestUri, '?');

    // Remove leading slash
    $requestUri = ltrim($requestUri, '/');
    
    // Split the URI into parts
    $uriParts = explode('/', $requestUri);

    // Include authentication routes
    if ($uriParts[0] === 'auth') {
        require_once __DIR__ . '/src/Auth/routes.php';
        exit;
    }

    // Default response for root endpoint
    if (empty($uriParts[0])) {
        echo json_encode(['message' => 'Welcome to the Dietitian Assist API']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
} 