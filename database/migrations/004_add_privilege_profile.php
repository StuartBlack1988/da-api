<?php

namespace DietitianAssist\Migration;

class AddPrivilegeProfile004 {
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
            $this->log("Starting migration 004: Add Privilege Profile");
            
            // Create PrivilegeProfile table
            $this->log("Creating PrivilegeProfile table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `PrivilegeProfile` (
                    `privilegeProfileId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_privilegeprofile_name` (`name`)
                )
            ");

            // Create PrivilegeProfilePrivileges table
            $this->log("Creating PrivilegeProfilePrivileges table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `PrivilegeProfilePrivileges` (
                    `privilegeProfilePrivilegeId` INT AUTO_INCREMENT PRIMARY KEY,
                    `privilegeProfileId` INT NOT NULL,
                    `privilegeId` INT NOT NULL,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`privilegeProfileId`) REFERENCES `PrivilegeProfile`(`privilegeProfileId`) ON DELETE CASCADE,
                    FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`) ON DELETE CASCADE,
                    UNIQUE KEY `idx_profile_privilege` (`privilegeProfileId`, `privilegeId`),
                    INDEX `idx_profileprivilege_profile` (`privilegeProfileId`),
                    INDEX `idx_profileprivilege_privilege` (`privilegeId`)
                )
            ");

            // Insert default privilege profiles
            $this->log("Inserting default privilege profiles");
            $db->exec("
                INSERT INTO `PrivilegeProfile` (`name`, `description`) VALUES
                ('admin', 'System administrator profile'),
                ('practice_manager', 'Practice manager profile'),
                ('dietitian', 'Dietitian profile'),
                ('receptionist', 'Receptionist profile'),
                ('client', 'Client/Patient profile')
            ");

            // Assign privileges to profiles
            $this->log("Assigning privileges to profiles");
            $db->exec("
                INSERT INTO `PrivilegeProfilePrivileges` (`privilegeProfileId`, `privilegeId`)
                SELECT pp.privilegeProfileId, p.privilegeId
                FROM `PrivilegeProfile` pp
                JOIN `Privileges` p ON pp.name = p.name
            ");

            // Record migration
            $this->log("Recording migration in SchemaVersion");
            $db->exec("
                INSERT INTO `SchemaVersion` (`version`, `description`) 
                VALUES ('004', 'Added privilege profiles')
            ");

            $this->log("Migration 004 completed successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 004: Add Privilege Profile");
            
            // Drop PrivilegeProfilePrivileges table
            $this->log("Dropping PrivilegeProfilePrivileges table");
            $db->exec("DROP TABLE IF EXISTS `PrivilegeProfilePrivileges`");
            
            // Drop PrivilegeProfile table
            $this->log("Dropping PrivilegeProfile table");
            $db->exec("DROP TABLE IF EXISTS `PrivilegeProfile`");

            $this->log("Rollback of migration 004 completed successfully");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 