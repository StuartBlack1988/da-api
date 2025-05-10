<?php

namespace DietitianAssist\Core;

class Response {
    public static function success($data = null, $message = 'Success') {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];
    }

    public static function error($message, $code = 400) {
        http_response_code($code);
        return [
            'status' => 'error',
            'message' => $message
        ];
    }

    public static function notFound($message = 'Resource not found') {
        return self::error($message, 404);
    }

    public static function unauthorized($message = 'Unauthorized') {
        return self::error($message, 401);
    }

    public static function forbidden($message = 'Forbidden') {
        return self::error($message, 403);
    }

    public static function validationError($errors) {
        return self::error('Validation failed', 422, ['errors' => $errors]);
    }
} 