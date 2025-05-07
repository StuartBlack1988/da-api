<?php

use DietitianAssist\ApiAuth\ApiAuthController;

$apiAuth = new ApiAuthController($db);

// Create API token (requires super admin role)
$router->post('/api-auth/create', function() use ($apiAuth) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        
        // Check if user has super role
        $stmt = $db->prepare("
            SELECT r.name as role 
            FROM `User` u 
            JOIN `Role` r ON u.roleId = r.roleId 
            WHERE u.userId = ?
        ");
        $stmt->execute([$decoded->sub]);
        $user = $stmt->fetch();
        
        if ($user['role'] !== 'super') {
            http_response_code(403);
            echo json_encode(['error' => 'Only super administrators can create API tokens']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($apiAuth->createApiToken($data, $decoded->sub));
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
});

// List API tokens (requires super admin role)
$router->get('/api-auth/list', function() use ($apiAuth) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        
        // Check if user has super role
        $stmt = $db->prepare("
            SELECT r.name as role 
            FROM `User` u 
            JOIN `Role` r ON u.roleId = r.roleId 
            WHERE u.userId = ?
        ");
        $stmt->execute([$decoded->sub]);
        $user = $stmt->fetch();
        
        if ($user['role'] !== 'super') {
            http_response_code(403);
            echo json_encode(['error' => 'Only super administrators can list API tokens']);
            return;
        }
        
        echo json_encode($apiAuth->listApiTokens());
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
});

// Deactivate API token (requires super admin role)
$router->post('/api-auth/deactivate/{id}', function($id) use ($apiAuth) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        
        // Check if user has super role
        $stmt = $db->prepare("
            SELECT r.name as role 
            FROM `User` u 
            JOIN `Role` r ON u.roleId = r.roleId 
            WHERE u.userId = ?
        ");
        $stmt->execute([$decoded->sub]);
        $user = $stmt->fetch();
        
        if ($user['role'] !== 'super') {
            http_response_code(403);
            echo json_encode(['error' => 'Only super administrators can deactivate API tokens']);
            return;
        }
        
        $result = $apiAuth->deactivateApiToken($id);
        if ($result) {
            echo json_encode(['message' => 'API token deactivated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to deactivate API token']);
        }
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}); 