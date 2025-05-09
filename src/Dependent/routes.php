<?php

use DietitianAssist\Dependent\DependentController;
use DietitianAssist\Auth\JwtAuth;

$dependentController = new DependentController($db);
$jwtAuth = new JwtAuth();

// Get all dependents for a user
$router->get('/dependents', function() use ($dependentController, $jwtAuth) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $decoded = $jwtAuth->validateToken($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $result = $dependentController->getDependents($decoded->userId);
    echo json_encode($result);
});

// Get a specific dependent
$router->get('/dependents/(\d+)', function($dependentId) use ($dependentController, $jwtAuth) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $decoded = $jwtAuth->validateToken($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $result = $dependentController->getDependent($decoded->userId, $dependentId);
    echo json_encode($result);
});

// Create a new dependent
$router->post('/dependents', function() use ($dependentController, $jwtAuth) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $decoded = $jwtAuth->validateToken($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $result = $dependentController->createDependent($decoded->userId, $data);
    echo json_encode($result);
});

// Update a dependent
$router->put('/dependents/(\d+)', function($dependentId) use ($dependentController, $jwtAuth) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $decoded = $jwtAuth->validateToken($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $result = $dependentController->updateDependent($decoded->userId, $dependentId, $data);
    echo json_encode($result);
});

// Deactivate a dependent
$router->post('/dependents/(\d+)/deactivate', function($dependentId) use ($dependentController, $jwtAuth) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $decoded = $jwtAuth->validateToken($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $result = $dependentController->deactivateDependent($decoded->userId, $dependentId);
    echo json_encode($result);
}); 