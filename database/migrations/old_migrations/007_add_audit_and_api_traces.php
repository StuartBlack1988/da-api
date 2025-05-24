<?php

namespace DietitianAssist\Migration;

use PDO;

class AddAuditAndApiTraces007 {
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
            $this->log("Starting migration 007: Add Audit and API Traces");

            // Create AuditLog table
            $this->log("Creating AuditLog table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS AuditLog (
                    auditLogId INT AUTO_INCREMENT PRIMARY KEY,
                    userId INT,
                    action VARCHAR(50) NOT NULL,
                    entityType VARCHAR(50) NOT NULL,
                    entityId INT,
                    oldValues JSON,
                    newValues JSON,
                    ipAddress VARCHAR(45),
                    userAgent TEXT,
                    createdDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (userId),
                    INDEX idx_entity (entityType, entityId),
                    INDEX idx_action (action),
                    INDEX idx_created (createdDate)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create ApiTrace table
            $this->log("Creating ApiTrace table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS ApiTrace (
                    apiTraceId INT AUTO_INCREMENT PRIMARY KEY,
                    userId INT,
                    method VARCHAR(10) NOT NULL,
                    endpoint VARCHAR(255) NOT NULL,
                    requestBody JSON,
                    responseBody JSON,
                    statusCode INT,
                    duration INT,
                    ipAddress VARCHAR(45),
                    userAgent TEXT,
                    createdDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (userId),
                    INDEX idx_endpoint (endpoint),
                    INDEX idx_method (method),
                    INDEX idx_status (statusCode),
                    INDEX idx_created (createdDate)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Record the migration
            $this->log("Recording migration in SchemaVersion");
            $stmt = $db->prepare("
                INSERT INTO SchemaVersion (version, description) 
                VALUES (?, ?)
            ");
            $stmt->execute(['007', 'Add Audit and API Traces tables']);

            $this->log("Migration 007 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 007: Add Audit and API Traces");

            // Drop ApiTrace table first (no foreign key dependencies)
            $this->log("Dropping ApiTrace table");
            $db->exec("DROP TABLE IF EXISTS ApiTrace");

            // Drop AuditLog table
            $this->log("Dropping AuditLog table");
            $db->exec("DROP TABLE IF EXISTS AuditLog");

            $this->log("Rollback of migration 007 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 