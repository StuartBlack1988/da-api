<?php

// System routes
$router->get('/system/info', function() {
    error_log("System info endpoint called");
    echo json_encode([
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'server_name' => $_SERVER['SERVER_NAME']
    ]);
});

$router->get('/system/db-test', function() {
    error_log("DB test endpoint called");
    try {
        $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASS'];
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $db = new PDO($dsn, $username, $password, $options);
        
        // Test query
        $stmt = $db->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Database connection successful',
            'mysql_version' => $result['version']
        ]);
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed'
        ]);
    }
}); 