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

            // Drop existing triggers
            $this->log("Dropping existing triggers for ApiAuth");
            $db->exec("DROP TRIGGER IF EXISTS trg_ApiAuth_insert_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_ApiAuth_update_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_ApiAuth_delete_audit");

            // Create INSERT trigger
            $this->log("Creating INSERT trigger for ApiAuth");
            try {
                $db->exec("
                    CREATE TRIGGER trg_ApiAuth_insert_audit
                    AFTER INSERT ON `ApiAuth`
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
                        'ApiAuth',
                        NEW.apiAuthId,
                        JSON_OBJECT(
                            'apiAuthId', NEW.apiAuthId,
                            'name', NEW.name,
                            'token', NEW.token,
                            'description', NEW.description,
                            'isActive', NEW.isActive,
                            'lastUsed', NEW.lastUsed,
                            'createdDate', NEW.createdDate,
                            'expiryDate', NEW.expiryDate,
                            'createdBy', NEW.createdBy
                        ),
                        @current_ip_address,
                        @current_user_agent
                    )
                ");
                $this->log("INSERT trigger created successfully");
            } catch (\Exception $e) {
                $this->log("ERROR creating INSERT trigger: " . $e->getMessage());
                throw $e;
            }

            // Create UPDATE trigger
            $this->log("Creating UPDATE trigger for ApiAuth");
            try {
                $db->exec("
                    CREATE TRIGGER trg_ApiAuth_update_audit
                    AFTER UPDATE ON `ApiAuth`
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
                        'ApiAuth',
                        NEW.apiAuthId,
                        JSON_OBJECT(
                            'apiAuthId', OLD.apiAuthId,
                            'name', OLD.name,
                            'token', OLD.token,
                            'description', OLD.description,
                            'isActive', OLD.isActive,
                            'lastUsed', OLD.lastUsed,
                            'createdDate', OLD.createdDate,
                            'expiryDate', OLD.expiryDate,
                            'createdBy', OLD.createdBy
                        ),
                        JSON_OBJECT(
                            'apiAuthId', NEW.apiAuthId,
                            'name', NEW.name,
                            'token', NEW.token,
                            'description', NEW.description,
                            'isActive', NEW.isActive,
                            'lastUsed', NEW.lastUsed,
                            'createdDate', NEW.createdDate,
                            'expiryDate', NEW.expiryDate,
                            'createdBy', NEW.createdBy
                        ),
                        @current_ip_address,
                        @current_user_agent
                    )
                ");
                $this->log("UPDATE trigger created successfully");
            } catch (\Exception $e) {
                $this->log("ERROR creating UPDATE trigger: " . $e->getMessage());
                throw $e;
            }

            // Create DELETE trigger
            $this->log("Creating DELETE trigger for ApiAuth");
            try {
                $db->exec("
                    CREATE TRIGGER trg_ApiAuth_delete_audit
                    BEFORE DELETE ON `ApiAuth`
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
                        'ApiAuth',
                        OLD.apiAuthId,
                        JSON_OBJECT(
                            'apiAuthId', OLD.apiAuthId,
                            'name', OLD.name,
                            'token', OLD.token,
                            'description', OLD.description,
                            'isActive', OLD.isActive,
                            'lastUsed', OLD.lastUsed,
                            'createdDate', OLD.createdDate,
                            'expiryDate', OLD.expiryDate,
                            'createdBy', OLD.createdBy
                        ),
                        @current_ip_address,
                        @current_user_agent
                    )
                ");
                $this->log("DELETE trigger created successfully");
            } catch (\Exception $e) {
                $this->log("ERROR creating DELETE trigger: " . $e->getMessage());
                throw $e;
            }

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