<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/config.php';

use DietitianAssist\Core\ApiResponse;

try {
    // Connect to database
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    echo "Connected to database successfully\n";

    // Start transaction
    $db->beginTransaction();
    echo "Started transaction\n";

    // Get all tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables\n";

    // Disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "Disabled foreign key checks\n";

    // Drop all tables
    foreach ($tables as $table) {
        $db->exec("DROP TABLE IF EXISTS `$table`");
        echo "Dropped table: $table\n";
    }

    // Enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Enabled foreign key checks\n";

    // Run initial migration
    require_once __DIR__ . '/migrations/001_initial_setup.php';
    $migration = new DietitianAssist\Migration\InitialSetup001();
    $migration->up($db);
    echo "Applied initial migration\n";

    // Commit transaction
    $db->commit();
    echo "Committed transaction\n";
    echo "Database reset complete!\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
        echo "Rolled back transaction due to error\n";
    }
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
} 