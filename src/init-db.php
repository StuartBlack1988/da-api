<?php

// Database configuration
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'dbname' => getenv('DB_NAME') ?: 'dietitian_assist',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4'
];

try {
    // Create PDO connection without database name first
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}",
        $dbConfig['user'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` CHARACTER SET {$dbConfig['charset']}");
    echo "Database created or already exists.\n";

    // Select the database
    $pdo->exec("USE `{$dbConfig['dbname']}`");

    // Read and execute schema file
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    echo "Schema created successfully.\n";

    // Run migrations
    require_once __DIR__ . '/migrate.php';
    echo "Database initialization completed successfully!\n";
} catch (\Exception $e) {
    echo "Error initializing database: " . $e->getMessage() . "\n";
    exit(1);
} 