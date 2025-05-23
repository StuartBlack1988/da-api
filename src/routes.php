// Database routes
$router->post('/api/database/reset', function() {
    $controller = new \DietitianAssist\Controllers\DatabaseController();
    return $controller->reset();
}); 