<?php

use App\User\UserController;

// Initialize User controller
$userController = new UserController($db);

// User routes
$router->post('/user/update', function() use ($userController) {
    // Get user ID from JWT token
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        $userId = $decoded->sub;
        
        // Get request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Update user
        $result = $userController->updateUser($data, $userId);
        
        if (isset($result['error'])) {
            http_response_code(400);
            echo json_encode($result);
            return;
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
});

$router->get('/user/profile', function() use ($userController) {
    // Get user ID from JWT token
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        $userId = $decoded->sub;
        
        // Get user profile
        $result = $userController->getUser($userId);
        
        if (isset($result['error'])) {
            http_response_code(404);
            echo json_encode($result);
            return;
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}); 