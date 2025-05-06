<?php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Create a custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    return false;
}

// Set the custom error handler
set_error_handler('customErrorHandler');

// Try to load the autoloader
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    // Include authentication routes
    require_once __DIR__ . '/src/Auth/routes.php';
    require_once __DIR__ . '/src/User/routes.php';
} catch (Exception $e) {
    error_log("Error loading .env: " . $e->getMessage());
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

    // Database test endpoint
    if ($uriParts[0] === 'system' && $uriParts[1] === 'db-test') {
        try {
            $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'];
            $username = $_ENV['DB_USER'];
            $password = $_ENV['DB_PASS'];
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $db = new PDO($dsn, $username, $password, $options);
            
            // Test query
            $stmt = $db->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Database connection successful',
                'mysql_version' => $result['version']
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed'
            ]);
            error_log("Database connection error: " . $e->getMessage());
        }
        exit;
    }

    // System info endpoint
    if ($uriParts[0] === 'system' && $uriParts[1] === 'info') {
        echo json_encode([
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
            'server_name' => $_SERVER['SERVER_NAME']
        ]);
        exit;
    }

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