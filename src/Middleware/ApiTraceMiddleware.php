<?php

namespace DietitianAssist\Middleware;

use PDO;

class ApiTraceMiddleware {
    private $db;
    private $startTime;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function handle() {
        $this->startTime = microtime(true);
        
        // Get request details
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_SERVER['REQUEST_URI'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Get request body
        $requestBody = null;
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $requestBody = file_get_contents('php://input');
        }
        
        // Get user ID from token if present
        $userId = null;
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            $stmt = $this->db->prepare("
                SELECT userId 
                FROM ApiAuth 
                WHERE token = ? AND isActive = TRUE 
                AND (expiryDate IS NULL OR expiryDate > NOW())
            ");
            $stmt->execute([$token]);
            $userId = $stmt->fetchColumn();
        }
        
        // Start output buffering to capture response
        ob_start();
        
        // Register shutdown function to log the trace
        register_shutdown_function(function() use ($method, $endpoint, $requestBody, $userId, $ipAddress, $userAgent) {
            $responseBody = ob_get_clean();
            $duration = round((microtime(true) - $this->startTime) * 1000); // Convert to milliseconds
            $statusCode = http_response_code();
            
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO ApiTrace (
                        userId,
                        method,
                        endpoint,
                        requestBody,
                        responseBody,
                        statusCode,
                        duration,
                        ipAddress,
                        userAgent
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $userId,
                    $method,
                    $endpoint,
                    $requestBody,
                    $responseBody,
                    $statusCode,
                    $duration,
                    $ipAddress,
                    $userAgent
                ]);
            } catch (\Exception $e) {
                error_log("Error logging API trace: " . $e->getMessage());
            }
            
            // Output the response
            echo $responseBody;
        });
    }
} 