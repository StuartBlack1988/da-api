<?php

namespace DietitianAssist\Middleware;

use PDO;

class ApiTraceMiddleware {
    private $db;
    private $startTime;
    private $logCallback;
    private $logFile;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->logFile = __DIR__ . '/../../logs/api_trace.log';
    }

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        // Log to callback if set
        if ($this->logCallback) {
            call_user_func($this->logCallback, $message);
        }
        
        // Also log directly to file
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function handle() {
        $this->log("ApiTraceMiddleware: Starting trace");
        $this->startTime = microtime(true);
        
        // Get request details
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_SERVER['REQUEST_URI'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $this->log("ApiTraceMiddleware: Request details - Method: $method, Endpoint: $endpoint");
        
        // Get request body
        $requestBody = null;
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $requestBody = file_get_contents('php://input');
            $this->log("ApiTraceMiddleware: Request body captured");
        }
        
        // Get user ID from token if present
        $userId = null;
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $this->log("ApiTraceMiddleware: Authorization header found");
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
                $this->log("ApiTraceMiddleware: User ID from token: " . ($userId ?: 'null'));
            } catch (\Exception $e) {
                $this->log("ApiTraceMiddleware: Error getting user ID: " . $e->getMessage());
            }
        } else {
            $this->log("ApiTraceMiddleware: No Authorization header found");
        }
        
        // Start output buffering to capture response
        ob_start();
        $this->log("ApiTraceMiddleware: Output buffering started");
        
        // Register shutdown function to log the trace
        register_shutdown_function(function() use ($method, $endpoint, $requestBody, $userId, $ipAddress, $userAgent) {
            $this->log("ApiTraceMiddleware: Shutdown function called");
            $responseBody = ob_get_clean();
            $duration = round((microtime(true) - $this->startTime) * 1000); // Convert to milliseconds
            $statusCode = http_response_code();
            
            $this->log("ApiTraceMiddleware: Preparing to insert trace - Status: $statusCode, Duration: {$duration}ms");
            
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
                
                $this->log("ApiTraceMiddleware: Executing insert with params: " . json_encode($params));
                
                $stmt->execute($params);
                $this->log("ApiTraceMiddleware: Trace inserted successfully");
            } catch (\Exception $e) {
                $this->log("ApiTraceMiddleware: Error logging API trace: " . $e->getMessage());
                $this->log("ApiTraceMiddleware: SQL State: " . $e->getCode());
                $this->log("ApiTraceMiddleware: Stack trace: " . $e->getTraceAsString());
            }
            
            // Output the response
            echo $responseBody;
            $this->log("ApiTraceMiddleware: Response output complete");
        });
        
        $this->log("ApiTraceMiddleware: Handle method complete");
    }
} 