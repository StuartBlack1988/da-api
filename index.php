<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use Bramus\Router\Router;

// Create Router instance
$router = new Router();

// Define routes
$router->get('/', function() {
    echo json_encode(['message' => 'Welcome to the Hello World API']);
});

$router->get('/hello', function() {
    echo json_encode(['message' => 'Hello World!']);
});

$router->get('/hello/{name}', function($name) {
    echo json_encode(['message' => "Hello, $name!"]);
});

// Set headers for JSON response
header('Content-Type: application/json');

// Run it!
$router->run(); 