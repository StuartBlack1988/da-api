<?php

namespace DietitianAssist\Migration;

use PDO;

class AddApiAuthTriggers003 {
    private $logCallback;
    private $startTime;

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        if ($this->logCallback) {
            $elapsed = microtime(true) - $this->startTime;
            call_user_func($this->logCallback, sprintf("[%.2fs] %s", $elapsed, $message));
        }
    }

    private function createAuditTrigger($db, $tableName) {
        try {
            $this->log("Starting trigger creation for {$tableName}");
            
            // Set a timeout for this specific operation
            $db->exec("SET SESSION wait_timeout = 5");
            $db->exec("SET SESSION interactive_timeout = 5");
            
            // Check if table exists
            $this->log("Checking if table {$tableName} exists");
            $stmt = $db->query("SHOW TABLES LIKE '{$tableName}'");
            if ($stmt->rowCount() === 0) {
                $this->log("Table {$tableName} does not exist, skipping trigger creation");
                return;
            }
            
            // Drop existing triggers
            $this->log("Dropping existing triggers for {$tableName}");
            $db->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_insert_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_update_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_delete_audit");

            // Get table columns
            $this->log("Getting columns for {$tableName}");
            $stmt = $db->query("SHOW COLUMNS FROM `{$tableName}`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->log("Found columns: " . implode(", ", $columns));

            // Get primary key column name
            $this->log("Getting primary key for {$tableName}");
            $stmt = $db->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            $primaryKey = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$primaryKey) {
                $this->log("No primary key found for {$tableName}, skipping trigger creation");
                return;
            }
            $idColumn = $primaryKey['Column_name'];
            $this->log("Primary key column: {$idColumn}");
            
            // Build JSON object pairs for NEW
            $newJsonPairs = [];
            foreach ($columns as $column) {
                $newJsonPairs[] = "'{$column}', NEW.{$column}";
            }
            $newJsonObject = implode(",\n        ", $newJsonPairs);

            // Build JSON object pairs for OLD
            $oldJsonPairs = [];
            foreach ($columns as $column) {
                $oldJsonPairs[] = "'{$column}', OLD.{$column}";
            }
            $oldJsonObject = implode(",\n        ", $oldJsonPairs);

            // Create INSERT trigger
            $this->log("Creating INSERT trigger for {$tableName}");
            $db->exec("
                CREATE TRIGGER trg_{$tableName}_insert_audit
                AFTER INSERT ON `{$tableName}`
                FOR EACH ROW
                INSERT INTO `AuditLog` (
                    userId,
                    action,
                    entityType,
                    entityId,
                    newValues,
                    ipAddress,
                    userAgent
                ) VALUES (
                    @current_user_id,
                    'INSERT',
                    '{$tableName}',
                    NEW.{$idColumn},
                    JSON_OBJECT(
                        {$newJsonObject}
                    ),
                    @current_ip_address,
                    @current_user_agent
                )
            ");

            // Create UPDATE trigger
            $this->log("Creating UPDATE trigger for {$tableName}");
            $db->exec("
                CREATE TRIGGER trg_{$tableName}_update_audit
                AFTER UPDATE ON `{$tableName}`
                FOR EACH ROW
                INSERT INTO `AuditLog` (
                    userId,
                    action,
                    entityType,
                    entityId,
                    oldValues,
                    newValues,
                    ipAddress,
                    userAgent
                ) VALUES (
                    @current_user_id,
                    'UPDATE',
                    '{$tableName}',
                    NEW.{$idColumn},
                    JSON_OBJECT(
                        {$oldJsonObject}
                    ),
                    JSON_OBJECT(
                        {$newJsonObject}
                    ),
                    @current_ip_address,
                    @current_user_agent
                )
            ");

            // Create DELETE trigger
            $this->log("Creating DELETE trigger for {$tableName}");
            $db->exec("
                CREATE TRIGGER trg_{$tableName}_delete_audit
                BEFORE DELETE ON `{$tableName}`
                FOR EACH ROW
                INSERT INTO `AuditLog` (
                    userId,
                    action,
                    entityType,
                    entityId,
                    oldValues,
                    ipAddress,
                    userAgent
                ) VALUES (
                    @current_user_id,
                    'DELETE',
                    '{$tableName}',
                    OLD.{$idColumn},
                    JSON_OBJECT(
                        {$oldJsonObject}
                    ),
                    @current_ip_address,
                    @current_user_agent
                )
            ");

            $this->log("Successfully created all triggers for {$tableName}");

        } catch (\Exception $e) {
            $this->log("ERROR creating triggers for {$tableName}: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function up($db) {
        try {
            $this->startTime = microtime(true);
            $this->log("Starting migration 003: Add ApiAuth Triggers");

            // Set timeout for this session
            $this->log("Setting session timeout to 30 seconds");
            $db->exec("SET SESSION wait_timeout = 30");
            $db->exec("SET SESSION interactive_timeout = 30");

            // Start transaction
            $this->log("Starting transaction");
            $db->exec("START TRANSACTION");

            // Create triggers for ApiAuth table
            $this->createAuditTrigger($db, 'ApiAuth');

            // Commit transaction
            $this->log("Committing transaction");
            $db->exec("COMMIT");

            $this->log("Migration 003 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            $db->exec("ROLLBACK");
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->startTime = microtime(true);
            $this->log("Starting rollback of migration 003: Add ApiAuth Triggers");

            // Drop triggers
            $this->log("Dropping ApiAuth triggers");
            $db->exec("DROP TRIGGER IF EXISTS trg_ApiAuth_insert_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_ApiAuth_update_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_ApiAuth_delete_audit");

            $this->log("Rollback of migration 003 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 