<?php

use DietitianAssist\Dashboard\DashboardController;

$dashboardController = new DashboardController($db);

// Get client dashboard metrics
$router->get('/dashboard/client-metrics', function() use ($dashboardController) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $token = $matches[1];
    $decoded = JWT::decode($token, JWT_SECRET_KEY, array('HS256'));
    
    if (!$decoded || !isset($decoded->userId)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $metrics = $dashboardController->getClientDashboardMetrics($decoded->userId);
    
    if (isset($metrics['error'])) {
        http_response_code(500);
        echo json_encode($metrics);
        return;
    }

    echo json_encode($metrics);
}); 