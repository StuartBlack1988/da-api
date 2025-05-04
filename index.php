<?php

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Log PHP version and environment information
error_log("PHP Version: " . PHP_VERSION);
error_log("Document Root: " . $_SERVER['DOCUMENT_ROOT']);
error_log("Script Filename: " . $_SERVER['SCRIPT_FILENAME']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Server Software: " . $_SERVER['SERVER_SOFTWARE']);
error_log("PHP Handler: " . php_sapi_name());
error_log("Error Log Path: " . __DIR__ . '/error.log');

// Test basic PHP functionality
try {
    error_log("Testing basic PHP functionality...");
    
    // Test file system access
    $testFile = __DIR__ . '/test.txt';
    file_put_contents($testFile, 'test');
    if (file_exists($testFile)) {
        error_log("File system access: OK");
        unlink($testFile);
    }
    
    // Test JSON
    error_log("Testing JSON encoding...");
    json_encode(['test' => 'test']);
    error_log("JSON encoding: OK");
    
    // Test error logging
    error_log("Error logging: OK");
    
} catch (Exception $e) {
    error_log("Basic functionality test failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
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
    
    // Log the processed URI
    error_log("Raw URI: " . $requestUri);
    
    // Split the URI into parts
    $uriParts = explode('/', $requestUri);
    error_log("URI Parts: " . print_r($uriParts, true));

    // Simple routing
    if (empty($uriParts[0])) {
        echo json_encode(['message' => 'Welcome to the Hello World API']);
    } elseif ($uriParts[0] === 'hello') {
        if (empty($uriParts[1])) {
            echo json_encode(['message' => 'Hello World!']);
        } else {
            $name = $uriParts[1];
            echo json_encode(['message' => "Hello, $name!"]);
        }
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