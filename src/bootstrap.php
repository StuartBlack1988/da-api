<?php

// Load configuration
$config = require __DIR__ . '/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Create PDO connection
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize logger
$logger = new DietitianAssist\Core\Logger($config['logging']['path']);

// Set up error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logger) {
    $logger->error($errstr, [
        'type' => $errno,
        'file' => $errfile,
        'line' => $errline
    ]);
    return false;
});

// Set up exception handler
set_exception_handler(function($e) use ($logger) {
    $logger->error($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
});

// Set up CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if (in_array($origin, $config['cors']['allowed_origins'])) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: ' . implode(', ', $config['cors']['allowed_methods']));
    header('Access-Control-Allow-Headers: ' . implode(', ', $config['cors']['allowed_headers']));
    header('Access-Control-Expose-Headers: ' . implode(', ', $config['cors']['exposed_headers']));
    header('Access-Control-Max-Age: ' . $config['cors']['max_age']);
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Validate API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
if (!$apiKey || $apiKey !== $config['api']['key']) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API key'
    ]);
    exit();
}

// Return initialized components
return [
    'pdo' => $pdo,
    'config' => $config,
    'logger' => $logger
]; 