<?php

use DietitianAssist\Migration\MigrationController;

// Initialize the migration controller
$migrationController = new MigrationController($db);

// Route for running migrations
$router->post('/migrations/run', function() use ($migrationController) {
    return $migrationController->runMigrations();
});

// Route for rolling back migrations
$router->post('/migrations/rollback', function() use ($migrationController) {
    return $migrationController->rollbackMigrations();
}); 