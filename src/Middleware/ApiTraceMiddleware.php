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
        
        // Ensure logs directory exists
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
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
        try {
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
                try {
                    $this->log("ApiTraceMiddleware: Shutdown function called");
                    $responseBody = ob_get_clean();
                    $duration = round((microtime(true) - $this->startTime) * 1000); // Convert to milliseconds
                    $statusCode = http_response_code();
                    
                    $this->log("ApiTraceMiddleware: Preparing to insert trace - Status: $statusCode, Duration: {$duration}ms");
                    
                    // Check if ApiTrace table exists
                    $stmt = $this->db->query("SHOW TABLES LIKE 'ApiTrace'");
                    if ($stmt->rowCount() === 0) {
                        $this->log("ApiTraceMiddleware: ERROR - ApiTrace table does not exist!");
                        throw new \Exception("ApiTrace table does not exist");
                    }
                    
                    // Check table structure
                    $stmt = $this->db->query("DESCRIBE ApiTrace");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $this->log("ApiTraceMiddleware: Table columns: " . implode(", ", $columns));
                    
                    try {
                        $this->log("ApiTraceMiddleware: Starting SQL insert");
                        
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
                        
                        $this->log("ApiTraceMiddleware: SQL statement prepared");
                        $this->log("ApiTraceMiddleware: Parameters: " . json_encode($params));
                        
                        $result = $stmt->execute($params);
                        $this->log("ApiTraceMiddleware: Execute result: " . ($result ? "true" : "false"));
                        
                        if ($result) {
                            $this->log("ApiTraceMiddleware: Trace inserted successfully. Last insert ID: " . $this->db->lastInsertId());
                        } else {
                            $this->log("ApiTraceMiddleware: Failed to insert trace. Error info: " . json_encode($stmt->errorInfo()));
                        }
                    } catch (\PDOException $e) {
                        $this->log("ApiTraceMiddleware: PDO Exception during insert: " . $e->getMessage());
                        $this->log("ApiTraceMiddleware: SQL State: " . $e->getCode());
                        $this->log("ApiTraceMiddleware: Stack trace: " . $e->getTraceAsString());
                    } catch (\Exception $e) {
                        $this->log("ApiTraceMiddleware: General Exception during insert: " . $e->getMessage());
                        $this->log("ApiTraceMiddleware: Stack trace: " . $e->getTraceAsString());
                    }
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
        } catch (\Exception $e) {
            $this->log("ApiTraceMiddleware: Fatal error in handle method: " . $e->getMessage());
            $this->log("ApiTraceMiddleware: Stack trace: " . $e->getTraceAsString());
        }
    }
} 