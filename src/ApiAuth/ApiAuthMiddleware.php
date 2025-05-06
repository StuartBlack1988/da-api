<?php

namespace App\ApiAuth;

class ApiAuthMiddleware {
    private $apiAuthController;
    private $excludedPaths = [
        '/auth/register',
        '/auth/login',
        '/auth/set-password',
        '/auth/reset-password',
        '/system/info',
        '/system/db-test'
    ];

    public function __construct($db) {
        $this->apiAuthController = new ApiAuthController($db);
    }

    public function handle($request) {
        // Skip API token validation for excluded paths
        if (in_array($request->getPath(), $this->excludedPaths)) {
            return true;
        }

        // Get API token from header
        $headers = getallheaders();
        $apiToken = $headers['X-API-Key'] ?? null;

        if (!$apiToken) {
            http_response_code(401);
            echo json_encode(['error' => 'API token is required']);
            return false;
        }

        // Validate API token
        $apiAuth = $this->apiAuthController->validateApiToken($apiToken);
        if (!$apiAuth) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired API token']);
            return false;
        }

        return true;
    }
} 