<?php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
    error_log("Error loading dependencies: " . $e->getMessage());
    throw $e;
}

// Initialize database connection
try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'];
    $username = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASS'];
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $db = new PDO($dsn, $username, $password, $options);
    $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 0); // Disable autocommit to support transactions
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    throw $e;
}

// Create router instance
try {
    $router = new \Bramus\Router\Router();
} catch (Exception $e) {
    error_log("Error initializing router: " . $e->getMessage());
    throw $e;
}

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize API Auth middleware
$apiAuthController = new \DietitianAssist\ApiAuth\ApiAuthController($db);
$apiAuthMiddleware = new \DietitianAssist\ApiAuth\ApiAuthMiddleware($apiAuthController);

// Initialize API Trace middleware
$apiTraceMiddleware = new \DietitianAssist\Middleware\ApiTraceMiddleware($db);
$apiTraceMiddleware->setLogCallback(function($message) {
    error_log($message);
});

// Global middleware to check API token and trace API calls
$router->before('GET|POST|PUT|DELETE', '/.*', function() use ($apiAuthMiddleware, $apiTraceMiddleware) {
    error_log("Router: Before middleware called");
    
    // Skip API token check for OPTIONS requests (CORS preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        error_log("Router: Skipping middleware for OPTIONS request");
        return;
    }
    
    // Handle API tracing
    error_log("Router: Calling ApiTraceMiddleware handle");
    $apiTraceMiddleware->handle();
    
    // Handle API auth
    error_log("Router: Calling ApiAuthMiddleware handle");
    if (!$apiAuthMiddleware->handle($_SERVER)) {
        error_log("Router: ApiAuthMiddleware failed");
        exit(); // ApiAuthMiddleware already sets response code and message
    }
    error_log("Router: Before middleware completed");
});

// Debug route - add this before loading other routes
$router->get('/system/debug', function() {
    echo json_encode([
        'headers' => getallheaders(),
        'server' => $_SERVER,
        'message' => 'Debug route working'
    ]);
});

// Include route files
try {
    require_once __DIR__ . '/src/System/routes.php';  // System routes first
    require_once __DIR__ . '/src/Auth/routes.php';
    require_once __DIR__ . '/src/User/routes.php';
    require_once __DIR__ . '/src/Migration/routes.php';  // Add migration routes
    require_once __DIR__ . '/src/Practice/routes.php';  // Add practice routes
} catch (Exception $e) {
    error_log("Error loading route files: " . $e->getMessage());
    throw $e;
}

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
try {
    $router->run();
} catch (Exception $e) {
    error_log("Router error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
} 