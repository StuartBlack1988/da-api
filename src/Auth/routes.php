<?php

use App\Auth\AuthController;

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
    
    error_log("DSN: " . $dsn);
    error_log("Username: " . $username);
    error_log("Password provided: " . ($password ? 'yes' : 'no'));
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $db = new PDO($dsn, $username, $password, $options);
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    throw $e;
}

$auth = new AuthController($db);

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Route the request
switch ($uriParts[0]) {
    case 'auth':
        switch ($uriParts[1] ?? '') {
            case 'register':
                echo json_encode($auth->register($data));
                break;
                
            case 'login':
                echo json_encode($auth->login($data));
                break;
                
            case 'set-password':
                echo json_encode($auth->setPassword($data));
                break;
                
            case 'update':
                // Get user ID from JWT token
                $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
                try {
                    $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
                    echo json_encode($auth->updateUser($data, $decoded->sub));
                } catch (\Exception $e) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid token']);
                }
                break;
                
            case 'reset-password':
                echo json_encode($auth->resetPassword($data));
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Not Found']);
                break;
        }
        break;
        
    default:
        // Handle other routes
        break;
} 