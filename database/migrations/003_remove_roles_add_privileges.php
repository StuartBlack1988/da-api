<?php

namespace DietitianAssist\Migration;

class RemoveRolesAddPrivileges003 {
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
            $this->log("Starting migration 003: Remove Roles Add Privileges");
            
            // Create Privileges table
            $this->log("Creating Privileges table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `Privileges` (
                    `privilegeId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_privilege_name` (`name`)
                )
            ");

            // Create UserPrivileges table
            $this->log("Creating UserPrivileges table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `UserPrivileges` (
                    `userPrivilegeId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT NOT NULL,
                    `privilegeId` INT NOT NULL,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`) ON DELETE CASCADE,
                    FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`) ON DELETE CASCADE,
                    UNIQUE KEY `idx_user_privilege` (`userId`, `privilegeId`),
                    INDEX `idx_userprivilege_user` (`userId`),
                    INDEX `idx_userprivilege_privilege` (`privilegeId`)
                )
            ");

            // Insert default privileges
            $this->log("Inserting default privileges");
            $db->exec("
                INSERT INTO `Privileges` (`name`, `description`) VALUES
                ('admin', 'System administrator privileges'),
                ('practice_manager', 'Practice manager privileges'),
                ('dietitian', 'Dietitian privileges'),
                ('receptionist', 'Receptionist privileges'),
                ('client', 'Client/Patient privileges')
            ");

            // Record migration
            $this->log("Recording migration in SchemaVersion");
            $db->exec("
                INSERT INTO `SchemaVersion` (`version`, `description`) 
                VALUES ('003', 'Removed roles and added privileges system')
            ");

            $this->log("Migration 003 completed successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 003: Remove Roles Add Privileges");
            
            // Drop UserPrivileges table
            $this->log("Dropping UserPrivileges table");
            $db->exec("DROP TABLE IF EXISTS `UserPrivileges`");
            
            // Drop Privileges table
            $this->log("Dropping Privileges table");
            $db->exec("DROP TABLE IF EXISTS `Privileges`");

            $this->log("Rollback of migration 003 completed successfully");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 