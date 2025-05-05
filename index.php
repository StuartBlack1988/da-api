<?php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create a custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $logFile = __DIR__ . '/debug.log';
    $message = date('Y-m-d H:i:s') . " - Error [$errno]: $errstr in $errfile on line $errline\n";
    file_put_contents($logFile, $message, FILE_APPEND);
    return false;
}

// Set the custom error handler
set_error_handler('customErrorHandler');

// Debug information
$debugInfo = [
    'PHP Version' => PHP_VERSION,
    'Current Directory' => __DIR__,
    'Vendor Path' => __DIR__ . '/vendor',
    'Bootstrap Path' => __DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php',
    'File Exists' => file_exists(__DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php') ? 'yes' : 'no',
    'Is Readable' => is_readable(__DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php') ? 'yes' : 'no',
    'File Permissions' => file_exists(__DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php') ? 
        substr(sprintf('%o', fileperms(__DIR__ . '/vendor/symfony/polyfill-ctype/bootstrap.php')), -4) : 'N/A'
];

// Write debug info to log
$logFile = __DIR__ . '/debug.log';
$logMessage = date('Y-m-d H:i:s') . " - Debug Information:\n";
foreach ($debugInfo as $key => $value) {
    $logMessage .= "$key: $value\n";
}
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Try to load the autoloader
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    throw $e;
}

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