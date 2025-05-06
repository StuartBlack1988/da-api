<?php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

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

    // Database test endpoint
    if ($uriParts[0] === 'system' && $uriParts[1] === 'db-test') {
        try {
            $db = new PDO(
                "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME'),
                getenv('DB_USER'),
                getenv('DB_PASS')
            );
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Test query
            $stmt = $db->query("SELECT 1");
            $result = $stmt->fetch();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Database connection successful',
                'db_host' => getenv('DB_HOST'),
                'db_name' => getenv('DB_NAME'),
                'db_user' => getenv('DB_USER')
            ], JSON_PRETTY_PRINT);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'db_host' => getenv('DB_HOST'),
                'db_name' => getenv('DB_NAME'),
                'db_user' => getenv('DB_USER')
            ], JSON_PRETTY_PRINT);
        }
        exit;
    }

    // System info endpoint
    if ($uriParts[0] === 'system' && $uriParts[1] === 'info') {
        echo json_encode([
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
            'server_name' => $_SERVER['SERVER_NAME'],
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'extensions' => get_loaded_extensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => ini_get('error_reporting')
        ], JSON_PRETTY_PRINT);
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