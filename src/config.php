<?php

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'dbname' => getenv('DB_NAME') ?: 'dietitian_assist',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4'
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'your-secret-key',
        'algorithm' => 'HS256',
        'access_token_expiry' => 3600, // 1 hour
        'refresh_token_expiry' => 604800 // 7 days
    ],
    'api' => [
        'key' => getenv('API_KEY') ?: 'your-api-key',
        'rate_limit' => [
            'requests' => 100,
            'period' => 60 // 1 minute
        ]
    ],
    'logging' => [
        'enabled' => true,
        'path' => dirname(__DIR__) . '/logs/app.log',
        'level' => 'debug' // debug, info, warning, error
    ],
    'cors' => [
        'allowed_origins' => explode(',', getenv('CORS_ORIGINS') ?: '*'),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-API-Key'],
        'exposed_headers' => ['X-Rate-Limit-Limit', 'X-Rate-Limit-Remaining', 'X-Rate-Limit-Reset'],
        'max_age' => 86400 // 24 hours
    ]
]; 