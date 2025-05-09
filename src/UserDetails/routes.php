<?php

use DietitianAssist\UserDetails\UserDetailsController;

$userDetails = new UserDetailsController($db);

// Get user details
$router->get('/user/details', function() use ($userDetails) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        echo json_encode($userDetails->getUserDetails($decoded->sub));
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
});

// Create user details
$router->post('/user/details', function() use ($userDetails) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        echo json_encode($userDetails->createUserDetails($decoded->sub, $data));
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
});

// Update user details
$router->put('/user/details', function() use ($userDetails) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        echo json_encode($userDetails->updateUserDetails($decoded->sub, $data));
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}); 