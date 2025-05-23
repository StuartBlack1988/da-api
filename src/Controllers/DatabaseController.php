<?php

namespace DietitianAssist\Controllers;

use DietitianAssist\Core\ApiResponse;
use DietitianAssist\Core\Database;
use DietitianAssist\Migration\InitialSetup001;
use PDO;

class DatabaseController {
    private $db;
    private $logs = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    private function log($message) {
        $this->logs[] = $message;
    }

    public function reset() {
        try {
            $this->log("Starting database reset");
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Disable foreign key checks
            $this->log("Disabling foreign key checks");
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Get all table names
            $this->log("Fetching all table names");
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            // Drop all tables
            foreach ($tables as $table) {
                $this->log("Dropping table: $table");
                $this->db->exec("DROP TABLE IF EXISTS `$table`");
            }
            
            // Enable foreign key checks
            $this->log("Enabling foreign key checks");
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Run initial migration
            $this->log("Running initial migration");
            $migration = new InitialSetup001();
            $migration->setLogCallback([$this, 'log']);
            $migration->up($this->db);
            
            // Commit transaction
            $this->db->commit();
            
            $this->log("Database reset completed successfully");
            
            return ApiResponse::success("Database reset completed successfully", [
                'logs' => $this->logs
            ]);

        } catch (\Exception $e) {
            // Rollback transaction
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            $this->log("Database reset failed: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            return ApiResponse::error("Database reset failed: " . $e->getMessage(), 500, [
                'logs' => $this->logs
            ]);
        }
    }
} 