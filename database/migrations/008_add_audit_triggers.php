<?php

namespace DietitianAssist\Migration;

use PDO;

class AddAuditTriggers008 {
    private $logCallback;

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        if ($this->logCallback) {
            call_user_func($this->logCallback, $message);
        }
    }

    private function createAuditTrigger($db, $tableName) {
        $this->log("Creating audit triggers for table: {$tableName}");

        // Get the primary key column name
        $stmt = $db->query("SHOW KEYS FROM {$tableName} WHERE Key_name = 'PRIMARY'");
        $primaryKey = $stmt->fetch(PDO::FETCH_ASSOC)['Column_name'];

        // Create INSERT trigger
        $db->exec("
            CREATE TRIGGER IF NOT EXISTS trg_{$tableName}_insert_audit
            AFTER INSERT ON {$tableName}
            FOR EACH ROW
            BEGIN
                INSERT INTO AuditLog (
                    userId,
                    action,
                    entityType,
                    entityId,
                    newValues,
                    ipAddress,
                    userAgent
                )
                VALUES (
                    @current_user_id,
                    'INSERT',
                    '{$tableName}',
                    NEW.{$primaryKey},
                    JSON_OBJECT(
                        " . $this->getColumnJsonPairs($db, $tableName, 'NEW') . "
                    ),
                    @current_ip_address,
                    @current_user_agent
                );
            END
        ");

        // Create UPDATE trigger
        $db->exec("
            CREATE TRIGGER IF NOT EXISTS trg_{$tableName}_update_audit
            AFTER UPDATE ON {$tableName}
            FOR EACH ROW
            BEGIN
                INSERT INTO AuditLog (
                    userId,
                    action,
                    entityType,
                    entityId,
                    oldValues,
                    newValues,
                    ipAddress,
                    userAgent
                )
                VALUES (
                    @current_user_id,
                    'UPDATE',
                    '{$tableName}',
                    NEW.{$primaryKey},
                    JSON_OBJECT(
                        " . $this->getColumnJsonPairs($db, $tableName, 'OLD') . "
                    ),
                    JSON_OBJECT(
                        " . $this->getColumnJsonPairs($db, $tableName, 'NEW') . "
                    ),
                    @current_ip_address,
                    @current_user_agent
                );
            END
        ");

        // Create DELETE trigger
        $db->exec("
            CREATE TRIGGER IF NOT EXISTS trg_{$tableName}_delete_audit
            BEFORE DELETE ON {$tableName}
            FOR EACH ROW
            BEGIN
                INSERT INTO AuditLog (
                    userId,
                    action,
                    entityType,
                    entityId,
                    oldValues,
                    ipAddress,
                    userAgent
                )
                VALUES (
                    @current_user_id,
                    'DELETE',
                    '{$tableName}',
                    OLD.{$primaryKey},
                    JSON_OBJECT(
                        " . $this->getColumnJsonPairs($db, $tableName, 'OLD') . "
                    ),
                    @current_ip_address,
                    @current_user_agent
                );
            END
        ");
    }

    private function getColumnJsonPairs($db, $tableName, $prefix) {
        $stmt = $db->query("SHOW COLUMNS FROM {$tableName}");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $pairs = [];
        foreach ($columns as $column) {
            $pairs[] = "'{$column}', {$prefix}.{$column}";
        }
        
        return implode(",\n                        ", $pairs);
    }

    private function dropAuditTriggers($db, $tableName) {
        $this->log("Dropping audit triggers for table: {$tableName}");
        
        $triggers = [
            "trg_{$tableName}_insert_audit",
            "trg_{$tableName}_update_audit",
            "trg_{$tableName}_delete_audit"
        ];

        foreach ($triggers as $trigger) {
            $db->exec("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    public function up($db) {
        try {
            $this->log("Starting migration 008: Add Audit Triggers");

            // Get list of all tables except AuditLog and ApiTrace
            $stmt = $db->query("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                AND table_name NOT IN ('AuditLog', 'ApiTrace', 'SchemaVersion')
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Create triggers for each table
            foreach ($tables as $table) {
                $this->createAuditTrigger($db, $table);
            }

            // Record the migration
            $this->log("Recording migration in SchemaVersion");
            $stmt = $db->prepare("
                INSERT INTO SchemaVersion (version, description) 
                VALUES (?, ?)
            ");
            $stmt->execute(['008', 'Add audit triggers for all tables']);

            $this->log("Migration 008 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 008: Add Audit Triggers");

            // Get list of all tables except AuditLog and ApiTrace
            $stmt = $db->query("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                AND table_name NOT IN ('AuditLog', 'ApiTrace', 'SchemaVersion')
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Drop triggers for each table
            foreach ($tables as $table) {
                $this->dropAuditTriggers($db, $table);
            }

            $this->log("Rollback of migration 008 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 