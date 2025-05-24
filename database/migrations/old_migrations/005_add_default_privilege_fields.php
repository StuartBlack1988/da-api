<?php

namespace DietitianAssist\Migration;

class AddDefaultPrivilegeFields005 {
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
            $this->log("Starting migration 005: Add Default Privilege Fields");
            
            // Add default privilege fields
            $this->log("Adding default privilege fields");
            $db->exec("
                INSERT INTO `Privileges` (`name`, `description`) VALUES
                ('make-bookings', 'Ability to create and manage bookings'),
                ('view-practice-bookings', 'Ability to view practice bookings'),
                ('view-practice-invoices', 'Ability to view practice invoices')
            ");

            // Record migration
            $this->log("Recording migration in SchemaVersion");
            $db->exec("
                INSERT INTO `SchemaVersion` (`version`, `description`) 
                VALUES ('005', 'Added default privilege fields')
            ");

            $this->log("Migration 005 completed successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 005: Add Default Privilege Fields");
            
            // Remove default privilege fields
            $this->log("Removing default privilege fields");
            $db->exec("
                DELETE FROM `Privileges` 
                WHERE `name` IN ('make-bookings', 'view-practice-bookings', 'view-practice-invoices')
            ");

            $this->log("Rollback of migration 005 completed successfully");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 