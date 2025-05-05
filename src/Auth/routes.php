<?php

use App\Auth\AuthController;

// Initialize database connection
$db = new PDO(
    "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
);

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
                    $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
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