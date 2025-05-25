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
        error_log("ApiTraceMiddleware: Starting trace");
        $this->startTime = microtime(true);
        
        // Get request details
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_SERVER['REQUEST_URI'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        error_log("ApiTraceMiddleware: Request details - Method: $method, Endpoint: $endpoint");
        
        // Get request body
        $requestBody = null;
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $requestBody = file_get_contents('php://input');
            error_log("ApiTraceMiddleware: Request body captured");
        }
        
        // Get user ID from token if present
        $userId = null;
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            error_log("ApiTraceMiddleware: Authorization header found");
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            try {
                $stmt = $this->db->prepare("
                    SELECT userId 
                    FROM ApiAuth 
                    WHERE token = ? AND isActive = TRUE 
                    AND (expiryDate IS NULL OR expiryDate > NOW())
                ");
                $stmt->execute([$token]);
                $userId = $stmt->fetchColumn();
                error_log("ApiTraceMiddleware: User ID from token: " . ($userId ?: 'null'));
            } catch (\Exception $e) {
                error_log("ApiTraceMiddleware: Error getting user ID: " . $e->getMessage());
            }
        } else {
            error_log("ApiTraceMiddleware: No Authorization header found");
        }
        
        // Start output buffering to capture response
        ob_start();
        error_log("ApiTraceMiddleware: Output buffering started");
        
        // Register shutdown function to log the trace
        register_shutdown_function(function() use ($method, $endpoint, $requestBody, $userId, $ipAddress, $userAgent) {
            error_log("ApiTraceMiddleware: Shutdown function called");
            $responseBody = ob_get_clean();
            $duration = round((microtime(true) - $this->startTime) * 1000); // Convert to milliseconds
            $statusCode = http_response_code();
            
            error_log("ApiTraceMiddleware: Preparing to insert trace - Status: $statusCode, Duration: {$duration}ms");
            
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
                
                $params = [
                    $userId,
                    $method,
                    $endpoint,
                    $requestBody,
                    $responseBody,
                    $statusCode,
                    $duration,
                    $ipAddress,
                    $userAgent
                ];
                
                error_log("ApiTraceMiddleware: Executing insert with params: " . json_encode($params));
                
                $stmt->execute($params);
                error_log("ApiTraceMiddleware: Trace inserted successfully");
            } catch (\Exception $e) {
                error_log("ApiTraceMiddleware: Error logging API trace: " . $e->getMessage());
                error_log("ApiTraceMiddleware: SQL State: " . $e->getCode());
                error_log("ApiTraceMiddleware: Stack trace: " . $e->getTraceAsString());
            }
            
            // Output the response
            echo $responseBody;
            error_log("ApiTraceMiddleware: Response output complete");
        });
        
        error_log("ApiTraceMiddleware: Handle method complete");
    }
} 