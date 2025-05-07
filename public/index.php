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

// Log all headers at the start
$headers = getallheaders();
error_log("Incoming request headers: " . print_r($headers, true));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Try to load the autoloader and environment
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    error_log("Autoloader loaded successfully");
    
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    error_log("Environment variables loaded successfully");
} catch (Exception $e) {
    error_log("Error loading dependencies: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
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
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $db = new PDO($dsn, $username, $password, $options);
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    throw $e;
}

// Create router instance
try {
    $router = new \Bramus\Router\Router();
    error_log("Router initialized successfully");
} catch (Exception $e) {
    error_log("Error initializing router: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
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
$apiAuthMiddleware = new \App\ApiAuth\ApiAuthMiddleware($db);

// Define public routes that don't require API token
$publicRoutes = [
    '/auth/login',
    '/auth/register',
    '/auth/reset-password',
    '/auth/set-password'
];

// Global middleware to check API token
$router->before('GET|POST|PUT|DELETE', '/.*', function() use ($apiAuthMiddleware, $publicRoutes) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Skip API token check for public routes
    if (in_array($requestPath, $publicRoutes)) {
        return;
    }
    
    // Skip API token check for OPTIONS requests (CORS preflight)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        return;
    }
    
    if (!$apiAuthMiddleware->handle($_SERVER)) {
        exit(); // ApiAuthMiddleware already sets response code and message
    }
});

// Debug route - add this before loading other routes
$router->get('/system/debug', function() use ($headers) {
    echo json_encode([
        'headers' => $headers,
        'server' => $_SERVER,
        'message' => 'Debug route working'
    ]);
});

// Include route files
try {
    error_log("Loading route files...");
    require_once __DIR__ . '/../src/System/routes.php';  // System routes first
    require_once __DIR__ . '/../src/Auth/routes.php';
    require_once __DIR__ . '/../src/User/routes.php';
    error_log("Route files loaded successfully");
} catch (Exception $e) {
    error_log("Error loading route files: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
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
    error_log("Starting router...");
    $router->run();
} catch (Exception $e) {
    error_log("Router error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
} 