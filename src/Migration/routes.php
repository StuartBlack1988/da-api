<?php

use DietitianAssist\Migration\MigrationController;

// Initialize the migration controller
$migrationController = new MigrationController($db);

// Route for running migrations
$router->post('/api/migrations/run', function() use ($migrationController) {
    $response = $migrationController->runMigrations();
    echo json_encode($response);
});

// Route for rolling back migrations
$router->post('/api/migrations/rollback', function() use ($migrationController) {
    $response = $migrationController->rollbackMigrations();
    echo json_encode($response);
}); 