<?php

namespace App\ApiAuth;

class ApiAuthMiddleware {
    private $apiAuthController;

    public function __construct($db) {
        $this->apiAuthController = new ApiAuthController($db);
    }

    public function handle($server) {
        // Get API token from header
        $headers = getallheaders();
        $apiToken = $headers['X-API-Key'] ?? null;

        // Log request details for debugging
        error_log("Request details:");
        error_log("- URI: " . $server['REQUEST_URI']);
        error_log("- Method: " . $server['REQUEST_METHOD']);
        error_log("- Headers present: " . implode(', ', array_keys($headers)));
        error_log("- API Token present: " . ($apiToken ? 'Yes' : 'No'));
        error_log("- API Token value: " . ($apiToken ? $apiToken : 'Not provided'));

        if (!$apiToken) {
            error_log("API token validation failed: No token provided");
            $this->sendUnauthorizedResponse('API token is required');
            return false;
        }

        // Validate API token
        try {
            error_log("Attempting to validate API token...");
            $apiAuth = $this->apiAuthController->validateApiToken($apiToken);
            error_log("API token validation result: " . ($apiAuth ? 'Success' : 'Failed'));
            
            if (!$apiAuth) {
                error_log("API token validation failed: Invalid or expired token");
                $this->sendUnauthorizedResponse('Invalid or expired API token');
                return false;
            }

            error_log("API token validated successfully");
            return true;
        } catch (\Exception $e) {
            error_log("API token validation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->sendUnauthorizedResponse('Error validating API token');
            return false;
        }
    }

    private function sendUnauthorizedResponse($message) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $message,
            'status' => 401,
            'timestamp' => date('c')
        ]);
    }
} 