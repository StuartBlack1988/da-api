<?php

namespace DietitianAssist\Migration;

class AddRoleTable006 {
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
            $this->log("Starting migration 006: AddRoleTable");
            
            // Set a longer lock timeout
            $this->log("Setting lock timeout and transaction isolation level");
            $db->exec("SET innodb_lock_wait_timeout = 50");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

            // Create Role table
            $this->log("Creating Role table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `Role` (
                    `roleId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_role_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("Role table created successfully");

            // Insert default roles one at a time
            $this->log("Inserting default roles");
            $insertRole = $db->prepare("
                INSERT INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ");

            $this->log("Inserting 'user' role");
            $insertRole->execute(['user', 'Regular user with standard permissions']);
            $this->log("Inserting 'super' role");
            $insertRole->execute(['super', 'Super user with elevated permissions']);
            $this->log("Default roles inserted successfully");

            // Get the user role ID
            $this->log("Fetching user role ID");
            $stmt = $db->prepare("SELECT roleId FROM Role WHERE name = ?");
            $stmt->execute(['user']);
            $userRoleId = $stmt->fetchColumn();
            $this->log("User role ID: " . $userRoleId);

            // Add roleId to User table without foreign key
            $this->log("Adding roleId column to User table");
            $db->exec("
                ALTER TABLE `User` 
                ADD COLUMN `roleId` INT NULL
            ");
            $this->log("roleId column added successfully");

            // Update users in smaller batches
            $this->log("Starting user role updates in batches");
            $updateUser = $db->prepare("
                UPDATE `User` 
                SET `roleId` = ? 
                WHERE `roleId` IS NULL 
                LIMIT 1000
            ");

            $batchCount = 0;
            do {
                $this->log("Processing batch " . ++$batchCount);
                $updateUser->execute([$userRoleId]);
                $affected = $updateUser->rowCount();
                $this->log("Updated " . $affected . " users in batch " . $batchCount);
            } while ($affected > 0);
            $this->log("All user role updates completed");

            // Make roleId NOT NULL
            $this->log("Making roleId column NOT NULL");
            $db->exec("
                ALTER TABLE `User` 
                MODIFY COLUMN `roleId` INT NOT NULL
            ");
            $this->log("roleId column set to NOT NULL");

            // Add foreign key constraint
            $this->log("Adding foreign key constraint");
            $db->exec("
                ALTER TABLE `User` 
                ADD CONSTRAINT `fk_user_role` 
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
            ");
            $this->log("Foreign key constraint added successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            // Reset settings
            $this->log("Resetting database settings");
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }

        // Reset settings
        $this->log("Resetting database settings");
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
        $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $this->log("Migration 006 completed successfully");
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 006: AddRoleTable");
            
            // Set a longer lock timeout
            $this->log("Setting lock timeout and transaction isolation level");
            $db->exec("SET innodb_lock_wait_timeout = 50");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

            // Remove foreign key constraint
            $this->log("Removing foreign key constraint");
            $db->exec("
                ALTER TABLE `User` 
                DROP FOREIGN KEY `fk_user_role`
            ");
            $this->log("Foreign key constraint removed");

            // Remove roleId column
            $this->log("Removing roleId column");
            $db->exec("
                ALTER TABLE `User` 
                DROP COLUMN `roleId`
            ");
            $this->log("roleId column removed");

            // Drop Role table
            $this->log("Dropping Role table");
            $db->exec("DROP TABLE IF EXISTS `Role`");
            $this->log("Role table dropped");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            // Reset settings
            $this->log("Resetting database settings");
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }

        // Reset settings
        $this->log("Resetting database settings");
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
        $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $this->log("Rollback of migration 006 completed successfully");
    }
} 