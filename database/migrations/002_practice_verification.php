<?php

namespace DietitianAssist\Migration;

class PracticeVerification002 {
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
            $this->log("Starting migration 002: Practice Verification");
            
            // Add verification fields to Practice table
            $this->log("Adding verification fields to Practice table");
            $db->exec("
                ALTER TABLE `Practice`
                ADD COLUMN `isVerified` BOOLEAN DEFAULT FALSE,
                ADD COLUMN `verificationDate` TIMESTAMP NULL,
                ADD COLUMN `verificationNotes` TEXT,
                ADD INDEX `idx_practice_verified` (`isVerified`)
            ");

            // Record migration
            $this->log("Recording migration in SchemaVersion");
            $db->exec("
                INSERT INTO `SchemaVersion` (`version`, `description`) 
                VALUES ('002', 'Added practice verification fields')
            ");

            $this->log("Migration 002 completed successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 002: Practice Verification");
            
            // Remove verification fields from Practice table
            $this->log("Removing verification fields from Practice table");
            $db->exec("
                ALTER TABLE `Practice`
                DROP INDEX `idx_practice_verified`,
                DROP COLUMN `isVerified`,
                DROP COLUMN `verificationDate`,
                DROP COLUMN `verificationNotes`
            ");

            $this->log("Rollback of migration 002 completed successfully");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 