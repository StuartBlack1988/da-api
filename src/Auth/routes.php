<?php

use DietitianAssist\Auth\AuthController;

// Debug environment variables
error_log("DB Connection attempt with:");
error_log("Host: " . $_ENV['DB_HOST']);
error_log("Database: " . $_ENV['DB_NAME']);
error_log("User: " . $_ENV['DB_USER']);
error_log("Password length: " . (isset($_ENV['DB_PASS']) ? strlen($_ENV['DB_PASS']) : 'not set'));

// Initialize database connection
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
    error_log("Database connection successful in auth routes");
} catch (PDOException $e) {
    error_log("Database connection error in auth routes: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    throw $e;
}

$auth = new AuthController($db);

// Auth routes
$router->post('/auth/register', function() use ($auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($auth->register($data));
});

$router->post('/auth/login', function() use ($auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($auth->login($data));
});

$router->post('/auth/set-password', function() use ($auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($auth->setPassword($data));
});

$router->post('/auth/update', function() use ($auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET_KEY'], ['HS256']);
        echo json_encode($auth->updateUser($data, $decoded->sub));
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
});

$router->post('/auth/reset-password', function() use ($auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($auth->resetPassword($data));
});

$router->post('/auth/validate-token', function() use ($auth) {
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($auth->validateTokenEndpoint($data));
});

// Create new patient
$router->post('/auth/create-patient', function() use ($auth) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception('Invalid request data');
        }

        $result = $auth->createPatient($data);
        http_response_code(201);
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}); 