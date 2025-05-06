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

// Try to load the autoloader and environment
try {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log("Error loading .env: " . $e->getMessage());
    throw $e;
}

// Create router instance
$router = new \Bramus\Router\Router();

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// System routes
$router->get('/system/info', function() {
    echo json_encode([
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'server_name' => $_SERVER['SERVER_NAME']
    ]);
});

$router->get('/system/db-test', function() {
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
});

// Include route files
require_once __DIR__ . '/src/Auth/routes.php';
require_once __DIR__ . '/src/User/routes.php';
require_once __DIR__ . '/src/Token/routes.php';
require_once __DIR__ . '/src/Role/routes.php';

// Default route
$router->get('/', function() {
    echo json_encode(['message' => 'Welcome to the Dietitian Assist API']);
});

// 404 handler
$router->set404(function() {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Not Found']);
});

// Run the router
$router->run(); 