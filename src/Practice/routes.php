<?php

use DietitianAssist\Practice\PracticeController;

// Initialize controller
$practiceController = new PracticeController($db);

// Practice routes
$router->post('/practice/register', function() use ($practiceController) {
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($practiceController->registerPractice($data));
}); 