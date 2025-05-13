<?php

namespace DietitianAssist\Core;

class ApiResponse {
    /**
     * Creates a success response
     * 
     * @param array $data The data to include in the response
     * @param int $statusCode The HTTP status code
     * @return array The response array
     */
    public static function success(array $data = [], int $statusCode = 200): array {
        http_response_code($statusCode);
        return [
            'status' => 'success',
            'data' => $data
        ];
    }

    /**
     * Creates an error response
     * 
     * @param string $message The error message
     * @param int $statusCode The HTTP status code
     * @return array The response array
     */
    public static function error(string $message, int $statusCode = 400): array {
        http_response_code($statusCode);
        return [
            'status' => 'error',
            'message' => $message
        ];
    }
} 