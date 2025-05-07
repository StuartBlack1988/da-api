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

        // Log the headers for debugging
        error_log("Request headers: " . print_r($headers, true));
        error_log("Request URI: " . $server['REQUEST_URI']);
        error_log("Request method: " . $server['REQUEST_METHOD']);

        if (!$apiToken) {
            error_log("No API token provided in request");
            http_response_code(401);
            echo json_encode(['error' => 'API token is required']);
            exit(); // Use exit() to ensure the request stops here
        }

        // Validate API token
        $apiAuth = $this->apiAuthController->validateApiToken($apiToken);
        if (!$apiAuth) {
            error_log("Invalid API token provided: " . $apiToken);
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired API token']);
            exit(); // Use exit() to ensure the request stops here
        }

        error_log("API token validated successfully");
        return true;
    }
} 