<?php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Debug .env file
error_log("Checking .env file:");
error_log("File exists: " . (file_exists(__DIR__ . '/.env') ? 'yes' : 'no'));
error_log("File readable: " . (is_readable(__DIR__ . '/.env') ? 'yes' : 'no'));
if (file_exists(__DIR__ . '/.env')) {
    error_log("File size: " . filesize(__DIR__ . '/.env') . " bytes");
    error_log("File permissions: " . substr(sprintf('%o', fileperms(__DIR__ . '/.env')), -4));
}

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
    
    // Debug environment variables after loading
    error_log("Environment variables after loading:");
    error_log("DB_HOST: " . ($_ENV['DB_HOST'] ?? 'not set'));
    error_log("DB_NAME: " . ($_ENV['DB_NAME'] ?? 'not set'));
    error_log("DB_USER: " . ($_ENV['DB_USER'] ?? 'not set'));
    error_log("DB_PASS length: " . (isset($_ENV['DB_PASS']) ? strlen($_ENV['DB_PASS']) : 'not set'));
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
                'connection_details' => [
                    'dsn' => $dsn,
                    'username' => $username,
                    'password_provided' => !empty($password),
                    'mysql_version' => $result['version'],
                    'php_pdo_drivers' => PDO::getAvailableDrivers()
                ],
                'environment_variables' => [
                    'DB_HOST' => $_ENV['DB_HOST'],
                    'DB_NAME' => $_ENV['DB_NAME'],
                    'DB_USER' => $_ENV['DB_USER'],
                    'DB_PASS_length' => strlen($_ENV['DB_PASS'])
                ]
            ], JSON_PRETTY_PRINT);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'connection_details' => [
                    'dsn' => $dsn,
                    'username' => $username,
                    'password_provided' => !empty($password)
                ],
                'environment_variables' => [
                    'DB_HOST' => $_ENV['DB_HOST'],
                    'DB_NAME' => $_ENV['DB_NAME'],
                    'DB_USER' => $_ENV['DB_USER'],
                    'DB_PASS_length' => strlen($_ENV['DB_PASS'])
                ]
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