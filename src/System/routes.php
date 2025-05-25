<?php

// Base controller for API token validation
class BaseController {
    protected $db;
    protected $apiAuthMiddleware;

    public function __construct($db) {
        $this->db = $db;
        $apiAuthController = new \DietitianAssist\ApiAuth\ApiAuthController($db);
        $this->apiAuthMiddleware = new \DietitianAssist\ApiAuth\ApiAuthMiddleware($apiAuthController);
    }

    protected function validateApiToken() {
        if (!$this->apiAuthMiddleware->handle($_SERVER)) {
            return false;
        }
        return true;
    }
}

// System controller
class SystemController extends BaseController {
    public function getSystemInfo() {
        if (!$this->validateApiToken()) {
            return;
        }
        
        error_log("System info endpoint called");
        echo json_encode([
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
            'server_name' => $_SERVER['SERVER_NAME']
        ]);
    }

    public function testDatabase() {
        if (!$this->validateApiToken()) {
            return;
        }
        
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
    }
}

// Initialize controller
$systemController = new SystemController($db);

// System routes
$router->get('/system/debug-headers', function() {
    error_log("Debug headers endpoint called");
    $headers = getallheaders();
    error_log("Headers: " . print_r($headers, true));
    echo json_encode([
        'headers' => $headers,
        'server' => $_SERVER
    ]);
});

$router->get('/system/info', function() {
    error_log("System info endpoint called");
    header('Content-Type: application/json');
    echo json_encode([
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'server_name' => $_SERVER['SERVER_NAME']
    ]);
});

$router->get('/system/db-test', function() use ($db) {
    error_log("DB test endpoint called");
    header('Content-Type: application/json');
    try {
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

$router->get('/system/test-trace', function() {
    error_log("Test trace endpoint called");
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Test trace endpoint',
        'timestamp' => date('Y-m-d H:i:s'),
        'request' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'headers' => getallheaders()
        ]
    ]);
}); 