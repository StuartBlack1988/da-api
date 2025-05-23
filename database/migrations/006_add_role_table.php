<?php

namespace DietitianAssist\Migration;

class AddRoleTable006 {
    private $logCallback;
    private $maxRetries = 3;
    private $retryDelay = 5; // seconds

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        if ($this->logCallback) {
            call_user_func($this->logCallback, $message);
        }
    }

    private function executeWithRetry($db, $sql, $params = [], $description = '') {
        $attempts = 0;
        while ($attempts < $this->maxRetries) {
            try {
                $this->log("Attempt " . ($attempts + 1) . " of " . $this->maxRetries . " for: " . $description);
                
                // Set aggressive lock timeout for this operation
                $db->exec("SET innodb_lock_wait_timeout = 10");
                $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
                
                if (empty($params)) {
                    $result = $db->exec($sql);
                } else {
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute($params);
                }
                
                // Reset settings
                $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
                $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
                
                $this->log("Successfully executed: " . $description);
                return $result;
            } catch (\Exception $e) {
                $attempts++;
                $this->log("Attempt " . $attempts . " failed: " . $e->getMessage());
                
                if ($attempts >= $this->maxRetries) {
                    throw $e;
                }
                
                $this->log("Waiting " . $this->retryDelay . " seconds before retry...");
                sleep($this->retryDelay);
            }
        }
    }

    public function up($db) {
        try {
            $this->log("Starting migration 006: AddRoleTable");
            
            // Create Role table
            $this->log("Creating Role table");
            $this->executeWithRetry($db, "
                CREATE TABLE IF NOT EXISTS `Role` (
                    `roleId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_role_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ", [], "Create Role table");

            // Insert default roles one at a time
            $this->log("Inserting default roles");
            $insertRole = $db->prepare("
                INSERT INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ");

            $this->log("Inserting 'user' role");
            $this->executeWithRetry($db, "
                INSERT INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ", ['user', 'Regular user with standard permissions'], "Insert user role");

            $this->log("Inserting 'super' role");
            $this->executeWithRetry($db, "
                INSERT INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ", ['super', 'Super user with elevated permissions'], "Insert super role");

            // Get the user role ID
            $this->log("Fetching user role ID");
            $stmt = $db->prepare("SELECT roleId FROM Role WHERE name = ?");
            $stmt->execute(['user']);
            $userRoleId = $stmt->fetchColumn();
            $this->log("User role ID: " . $userRoleId);

            // Add roleId to User table without foreign key
            $this->log("Adding roleId column to User table");
            $this->executeWithRetry($db, "
                ALTER TABLE `User` 
                ADD COLUMN `roleId` INT NULL
            ", [], "Add roleId column");

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
                $this->executeWithRetry($db, "
                    UPDATE `User` 
                    SET `roleId` = ? 
                    WHERE `roleId` IS NULL 
                    LIMIT 1000
                ", [$userRoleId], "Update user batch " . $batchCount);
                
                $affected = $updateUser->rowCount();
                $this->log("Updated " . $affected . " users in batch " . $batchCount);
            } while ($affected > 0);
            $this->log("All user role updates completed");

            // Make roleId NOT NULL
            $this->log("Making roleId column NOT NULL");
            $this->executeWithRetry($db, "
                ALTER TABLE `User` 
                MODIFY COLUMN `roleId` INT NOT NULL
            ", [], "Make roleId NOT NULL");

            // Add foreign key constraint
            $this->log("Adding foreign key constraint");
            $this->executeWithRetry($db, "
                ALTER TABLE `User` 
                ADD CONSTRAINT `fk_user_role` 
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
            ", [], "Add foreign key constraint");

            $this->log("Migration 006 completed successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            // Reset settings
            $this->log("Resetting database settings");
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 006: AddRoleTable");
            
            // Remove foreign key constraint
            $this->log("Removing foreign key constraint");
            $this->executeWithRetry($db, "
                ALTER TABLE `User` 
                DROP FOREIGN KEY `fk_user_role`
            ", [], "Remove foreign key constraint");

            // Remove roleId column
            $this->log("Removing roleId column");
            $this->executeWithRetry($db, "
                ALTER TABLE `User` 
                DROP COLUMN `roleId`
            ", [], "Remove roleId column");

            // Drop Role table
            $this->log("Dropping Role table");
            $this->executeWithRetry($db, "DROP TABLE IF EXISTS `Role`", [], "Drop Role table");

            $this->log("Rollback of migration 006 completed successfully");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            // Reset settings
            $this->log("Resetting database settings");
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }
    }
} 