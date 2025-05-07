<?php

namespace App\ApiAuth;

class ApiAuthMiddleware {
    private $apiAuthController;

    public function __construct($db) {
        $this->apiAuthController = new ApiAuthController($db);
    }

    public function handle($request) {
        // Get API token from header
        $headers = getallheaders();
        $apiToken = $headers['X-API-Key'] ?? null;

        // Log the headers for debugging
        error_log("Request headers: " . print_r($headers, true));

        if (!$apiToken) {
            error_log("No API token provided in request");
            http_response_code(401);
            echo json_encode(['error' => 'API token is required']);
            return false;
        }

        // Validate API token
        $apiAuth = $this->apiAuthController->validateApiToken($apiToken);
        if (!$apiAuth) {
            error_log("Invalid API token provided: " . $apiToken);
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired API token']);
            return false;
        }

        error_log("API token validated successfully");
        return true;
    }
} 