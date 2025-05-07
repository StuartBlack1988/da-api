<?php

namespace DietitianAssist\ApiAuth;

class ApiAuthMiddleware {
    private $controller;

    public function __construct(ApiAuthController $controller) {
        $this->controller = $controller;
    }

    public function handleRequest(array $headers): bool {
        // Check if API key is present in headers
        if (!isset($headers['HTTP_X_API_KEY'])) {
            return false;
        }

        $apiToken = $headers['HTTP_X_API_KEY'];
        return $this->controller->validateApiToken($apiToken);
    }

    public function handle($server) {
        // Get API token from server array
        $apiToken = $server['HTTP_X_API_KEY'] ?? null;

        if (!$apiToken) {
            error_log("API token validation failed: No token provided");
            $this->sendUnauthorizedResponse('API token is required');
            return false;
        }

        // Validate API token
        try {
            $apiAuth = $this->controller->validateApiToken($apiToken);
            
            if (!$apiAuth) {
                error_log("API token validation failed: Invalid or expired token");
                $this->sendUnauthorizedResponse('Invalid or expired API token');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("API token validation error: " . $e->getMessage());
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