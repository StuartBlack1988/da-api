<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DietitianAssist\Migration\AddIndexes;

// Database configuration
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'dbname' => getenv('DB_NAME') ?: 'dietitian_assist',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4'
];

try {
    // Create PDO connection
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    echo "Running migrations...\n";

    // Run AddIndexes migration
    $migration = new AddIndexes($pdo);
    $migration->up();

    echo "Migrations completed successfully!\n";
} catch (\Exception $e) {
    echo "Error running migrations: " . $e->getMessage() . "\n";
    exit(1);
} 