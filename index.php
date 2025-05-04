<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json');

// Get the request URI and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string if present
$requestUri = strtok($requestUri, '?');

// Remove base path if present
$basePath = '/';
$requestUri = str_replace($basePath, '', $requestUri);

// Simple routing
switch ($requestUri) {
    case '':
    case '/':
        echo json_encode(['message' => 'Welcome to the Hello World API']);
        break;
        
    case 'hello':
        echo json_encode(['message' => 'Hello World!']);
        break;
        
    default:
        // Check for /hello/{name} pattern
        if (preg_match('/^hello\/(.+)$/', $requestUri, $matches)) {
            $name = $matches[1];
            echo json_encode(['message' => "Hello, $name!"]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
        }
        break;
}

phpinfo(); 