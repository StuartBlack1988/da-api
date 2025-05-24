<?php

namespace DietitianAssist\Migration;

use PDO;

class CoreTables002 {
    private $logCallback;

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        if ($this->logCallback) {
            call_user_func($this->logCallback, $message);
        }
    }

    public function up($db) {
        try {
            $this->log("Starting migration 002: Core Tables");

            // Create Practice table
            $this->log("Creating Practice table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `Practice` (
                    `practiceId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `email` VARCHAR(75) NOT NULL,
                    `phone` VARCHAR(20),
                    `address` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_practice_name` (`name`),
                    INDEX `idx_practice_email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create PracticeUser table
            $this->log("Creating PracticeUser table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `PracticeUser` (
                    `practiceUserId` INT AUTO_INCREMENT PRIMARY KEY,
                    `practiceId` INT NOT NULL,
                    `userId` INT NOT NULL,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`practiceId`) REFERENCES `Practice`(`practiceId`),
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`),
                    INDEX `idx_practiceuser_practice` (`practiceId`),
                    INDEX `idx_practiceuser_user` (`userId`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create AuditLog table
            $this->log("Creating AuditLog table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `AuditLog` (
                    `auditLogId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT,
                    `action` VARCHAR(50) NOT NULL,
                    `tableName` VARCHAR(100) NOT NULL,
                    `recordId` INT,
                    `oldValues` JSON,
                    `newValues` JSON,
                    `ipAddress` VARCHAR(45),
                    `userAgent` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`),
                    INDEX `idx_auditlog_user` (`userId`),
                    INDEX `idx_auditlog_action` (`action`),
                    INDEX `idx_auditlog_table` (`tableName`),
                    INDEX `idx_auditlog_record` (`recordId`),
                    INDEX `idx_auditlog_created` (`createdDate`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create ApiTrace table
            $this->log("Creating ApiTrace table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `ApiTrace` (
                    `apiTraceId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT,
                    `method` VARCHAR(10) NOT NULL,
                    `endpoint` VARCHAR(255) NOT NULL,
                    `requestBody` JSON,
                    `responseBody` JSON,
                    `statusCode` INT,
                    `ipAddress` VARCHAR(45),
                    `userAgent` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`),
                    INDEX `idx_apitrace_user` (`userId`),
                    INDEX `idx_apitrace_method` (`method`),
                    INDEX `idx_apitrace_endpoint` (`endpoint`),
                    INDEX `idx_apitrace_status` (`statusCode`),
                    INDEX `idx_apitrace_created` (`createdDate`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create audit triggers for all tables
            $this->log("Creating audit triggers");
            $tables = [
                'Practice', 'PracticeUser', 'AuditLog', 'ApiTrace'
            ];

            foreach ($tables as $table) {
                $this->createAuditTrigger($db, $table);
            }

            // Record the migration
            $this->log("Recording migration in SchemaVersion");
            $stmt = $db->prepare("
                INSERT INTO SchemaVersion (version, description) 
                VALUES (?, ?)
            ");
            $stmt->execute(['002', 'Core tables setup with Practice, PracticeUser, and audit tables']);

            $this->log("Migration 002 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
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

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 002: Core Tables");

            // Drop tables in reverse order of dependencies
            $tables = [
                'ApiTrace',
                'AuditLog',
                'PracticeUser',
                'Practice'
            ];

            foreach ($tables as $table) {
                $this->log("Dropping table: {$table}");
                $db->exec("DROP TABLE IF EXISTS `{$table}`");
            }

            $this->log("Rollback of migration 002 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 