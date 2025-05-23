<?php

namespace DietitianAssist\Migration;

class AddRoleTable006 {
    public function up($db) {
        try {
            error_log("Starting migration 006: AddRoleTable");
            
            // Set a longer lock timeout
            error_log("Setting lock timeout and transaction isolation level");
            $db->exec("SET innodb_lock_wait_timeout = 50");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

            // Create Role table
            error_log("Creating Role table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `Role` (
                    `roleId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_role_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            error_log("Role table created successfully");

            // Insert default roles one at a time
            error_log("Inserting default roles");
            $insertRole = $db->prepare("
                INSERT INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ");

            error_log("Inserting 'user' role");
            $insertRole->execute(['user', 'Regular user with standard permissions']);
            error_log("Inserting 'super' role");
            $insertRole->execute(['super', 'Super user with elevated permissions']);
            error_log("Default roles inserted successfully");

            // Get the user role ID
            error_log("Fetching user role ID");
            $stmt = $db->prepare("SELECT roleId FROM Role WHERE name = ?");
            $stmt->execute(['user']);
            $userRoleId = $stmt->fetchColumn();
            error_log("User role ID: " . $userRoleId);

            // Add roleId to User table without foreign key
            error_log("Adding roleId column to User table");
            $db->exec("
                ALTER TABLE `User` 
                ADD COLUMN `roleId` INT NULL
            ");
            error_log("roleId column added successfully");

            // Update users in smaller batches
            error_log("Starting user role updates in batches");
            $updateUser = $db->prepare("
                UPDATE `User` 
                SET `roleId` = ? 
                WHERE `roleId` IS NULL 
                LIMIT 1000
            ");

            $batchCount = 0;
            do {
                error_log("Processing batch " . ++$batchCount);
                $updateUser->execute([$userRoleId]);
                $affected = $updateUser->rowCount();
                error_log("Updated " . $affected . " users in batch " . $batchCount);
            } while ($affected > 0);
            error_log("All user role updates completed");

            // Make roleId NOT NULL
            error_log("Making roleId column NOT NULL");
            $db->exec("
                ALTER TABLE `User` 
                MODIFY COLUMN `roleId` INT NOT NULL
            ");
            error_log("roleId column set to NOT NULL");

            // Add foreign key constraint
            error_log("Adding foreign key constraint");
            $db->exec("
                ALTER TABLE `User` 
                ADD CONSTRAINT `fk_user_role` 
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
            ");
            error_log("Foreign key constraint added successfully");

        } catch (\Exception $e) {
            error_log("Migration failed at: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Reset settings
            error_log("Resetting database settings");
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }

        // Reset settings
        error_log("Resetting database settings");
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
        $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        error_log("Migration 006 completed successfully");
    }

    public function down($db) {
        try {
            error_log("Starting rollback of migration 006: AddRoleTable");
            
            // Set a longer lock timeout
            error_log("Setting lock timeout and transaction isolation level");
            $db->exec("SET innodb_lock_wait_timeout = 50");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

            // Remove foreign key constraint
            error_log("Removing foreign key constraint");
            $db->exec("
                ALTER TABLE `User` 
                DROP FOREIGN KEY `fk_user_role`
            ");
            error_log("Foreign key constraint removed");

            // Remove roleId column
            error_log("Removing roleId column");
            $db->exec("
                ALTER TABLE `User` 
                DROP COLUMN `roleId`
            ");
            error_log("roleId column removed");

            // Drop Role table
            error_log("Dropping Role table");
            $db->exec("DROP TABLE IF EXISTS `Role`");
            error_log("Role table dropped");

        } catch (\Exception $e) {
            error_log("Rollback failed at: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Reset settings
            error_log("Resetting database settings");
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }

        // Reset settings
        error_log("Resetting database settings");
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
        $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        error_log("Rollback of migration 006 completed successfully");
    }
} 