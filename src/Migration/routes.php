<?php

use DietitianAssist\Migration\MigrationController;

// Migration routes
$router->post('/api/migrate', function() use ($db) {
    $controller = new MigrationController($db);
    $request = json_decode(file_get_contents('php://input'), true);
    $result = $controller->handleMigration($request);
    header('Content-Type: application/json');
    echo json_encode($result);
}); 