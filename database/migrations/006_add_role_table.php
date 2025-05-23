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
                $db->exec("SET innodb_lock_wait_timeout = 30");
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

    private function dropForeignKeys($db, $tableName) {
        $this->log("Finding foreign keys referencing table: " . $tableName);
        
        // Get all foreign keys referencing this table
        $stmt = $db->query("
            SELECT 
                TABLE_NAME,
                CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_NAME = '$tableName'
            AND REFERENCED_TABLE_SCHEMA = DATABASE()
        ");
        
        $foreignKeys = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($foreignKeys as $fk) {
            $this->log("Dropping foreign key: " . $fk['CONSTRAINT_NAME'] . " from table: " . $fk['TABLE_NAME']);
            $this->executeWithRetry($db, 
                "ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`",
                [],
                "Drop foreign key {$fk['CONSTRAINT_NAME']}"
            );
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
            $this->log("Inserting 'user' role");
            $this->executeWithRetry($db, "
                INSERT IGNORE INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ", ['user', 'Regular user with standard permissions'], "Insert user role");

            $this->log("Inserting 'super' role");
            $this->executeWithRetry($db, "
                INSERT IGNORE INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ", ['super', 'Super user with elevated permissions'], "Insert super role");

            // Get the user role ID
            $this->log("Fetching user role ID");
            $stmt = $db->prepare("SELECT roleId FROM Role WHERE name = ?");
            $stmt->execute(['user']);
            $userRoleId = $stmt->fetchColumn();
            $this->log("User role ID: " . $userRoleId);

            // Create new User table with roleId
            $this->log("Creating new User table with roleId");
            $this->executeWithRetry($db, "
                CREATE TABLE `User_new` (
                    `userId` INT AUTO_INCREMENT PRIMARY KEY,
                    `password` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,
                    `email` VARCHAR(75) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                    `name` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                    `surname` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `lastLogin` TIMESTAMP NULL,
                    `userStatusId` INT NOT NULL,
                    `roleId` INT NOT NULL,
                    FOREIGN KEY (`userStatusId`) REFERENCES `UserStatus`(`userStatusId`),
                    FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`),
                    INDEX `idx_user_email` (`email`),
                    INDEX `idx_user_name_surname` (`name`, `surname`),
                    INDEX `idx_user_status` (`userStatusId`),
                    INDEX `idx_user_lastlogin` (`lastLogin`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ", [], "Create new User table");

            // Copy data to new table
            $this->log("Copying data to new User table");
            $this->executeWithRetry($db, "
                INSERT INTO `User_new` (
                    `userId`, `password`, `email`, `name`, `surname`, 
                    `createdDate`, `modifiedDate`, `lastLogin`, `userStatusId`, `roleId`
                )
                SELECT 
                    `userId`, `password`, `email`, `name`, `surname`, 
                    `createdDate`, `modifiedDate`, `lastLogin`, `userStatusId`, ?
                FROM `User`
            ", [$userRoleId], "Copy data to new User table");

            // Drop foreign keys before dropping the old table
            $this->dropForeignKeys($db, 'User');

            // Drop old table and rename new one in separate steps
            $this->log("Dropping old User table");
            $this->executeWithRetry($db, "DROP TABLE `User`", [], "Drop old User table");

            $this->log("Renaming new User table");
            $this->executeWithRetry($db, "RENAME TABLE `User_new` TO `User`", [], "Rename new User table");

            $this->log("Migration 006 completed successfully");

        } catch (\Exception $e) {
            $this->log("Migration failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            // Clean up on failure
            $this->log("Cleaning up on failure");
            try {
                $db->exec("DROP TABLE IF EXISTS `User_new`");
            } catch (\Exception $cleanupError) {
                $this->log("Cleanup error: " . $cleanupError->getMessage());
            }
            
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 006: AddRoleTable");
            
            // Create new User table without roleId
            $this->log("Creating new User table without roleId");
            $this->executeWithRetry($db, "
                CREATE TABLE `User_new` (
                    `userId` INT AUTO_INCREMENT PRIMARY KEY,
                    `password` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,
                    `email` VARCHAR(75) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                    `name` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                    `surname` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `lastLogin` TIMESTAMP NULL,
                    `userStatusId` INT NOT NULL,
                    FOREIGN KEY (`userStatusId`) REFERENCES `UserStatus`(`userStatusId`),
                    INDEX `idx_user_email` (`email`),
                    INDEX `idx_user_name_surname` (`name`, `surname`),
                    INDEX `idx_user_status` (`userStatusId`),
                    INDEX `idx_user_lastlogin` (`lastLogin`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ", [], "Create new User table");

            // Copy data to new table
            $this->log("Copying data to new User table");
            $this->executeWithRetry($db, "
                INSERT INTO `User_new` (
                    `userId`, `password`, `email`, `name`, `surname`, 
                    `createdDate`, `modifiedDate`, `lastLogin`, `userStatusId`
                )
                SELECT 
                    `userId`, `password`, `email`, `name`, `surname`, 
                    `createdDate`, `modifiedDate`, `lastLogin`, `userStatusId`
                FROM `User`
            ", [], "Copy data to new User table");

            // Drop foreign keys before dropping the old table
            $this->dropForeignKeys($db, 'User');

            // Drop old table and rename new one in separate steps
            $this->log("Dropping old User table");
            $this->executeWithRetry($db, "DROP TABLE `User`", [], "Drop old User table");

            $this->log("Renaming new User table");
            $this->executeWithRetry($db, "RENAME TABLE `User_new` TO `User`", [], "Rename new User table");

            // Drop Role table
            $this->log("Dropping Role table");
            $this->executeWithRetry($db, "DROP TABLE IF EXISTS `Role`", [], "Drop Role table");

            $this->log("Rollback of migration 006 completed successfully");

        } catch (\Exception $e) {
            $this->log("Rollback failed at: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            
            // Clean up on failure
            $this->log("Cleaning up on failure");
            try {
                $db->exec("DROP TABLE IF EXISTS `User_new`");
            } catch (\Exception $cleanupError) {
                $this->log("Cleanup error: " . $cleanupError->getMessage());
            }
            
            throw $e;
        }
    }
} 