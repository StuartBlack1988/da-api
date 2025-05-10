<?php

use DietitianAssist\User\UserController;
use DietitianAssist\ApiAuth\ApiAuthMiddleware;

// Initialize User controller
$userController = new UserController($db);

// List users with filtering and pagination
$router->get('/user/list', function() use ($userController) {
    $filters = [];
    
    // Get filter parameters
    if (isset($_GET['role'])) {
        $filters['role'] = $_GET['role'];
    }
    if (isset($_GET['isActive'])) {
        $filters['isActive'] = filter_var($_GET['isActive'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }

    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;

    $result = $userController->listUsers($filters, $page, $limit);
    
    if ($result['status'] === 'error') {
        http_response_code(400);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
});

// Get user profile (authenticated)
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

// Update user (authenticated)
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

// Update user (admin)
$router->put('/user/update/{userId}', function($userId) use ($userController) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error' => 'Invalid request body'
        ]);
        return;
    }

    $result = $userController->updateUser($userId, $data);
    
    if ($result['status'] === 'error') {
        http_response_code(400);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
});

// Deactivate user
$router->post('/user/deactivate/{userId}', function($userId) use ($userController) {
    $result = $userController->deactivateUser($userId);
    
    if ($result['status'] === 'error') {
        http_response_code(400);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
});

// Reactivate user
$router->post('/user/reactivate/{userId}', function($userId) use ($userController) {
    $result = $userController->reactivateUser($userId);
    
    if ($result['status'] === 'error') {
        http_response_code(400);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}); 