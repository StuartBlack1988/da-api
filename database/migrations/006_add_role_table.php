<?php

namespace DietitianAssist\Migration;

class AddRoleTable006 {
    public function up($db) {
        try {
            // Set a longer lock timeout
            $db->exec("SET innodb_lock_wait_timeout = 50");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

            // Create Role table
            $db->exec("
                CREATE TABLE IF NOT EXISTS `Role` (
                    `roleId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_role_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Insert default roles one at a time
            $insertRole = $db->prepare("
                INSERT INTO `Role` (`name`, `description`) 
                VALUES (?, ?)
            ");

            $insertRole->execute(['user', 'Regular user with standard permissions']);
            $insertRole->execute(['super', 'Super user with elevated permissions']);

            // Get the user role ID
            $stmt = $db->prepare("SELECT roleId FROM Role WHERE name = ?");
            $stmt->execute(['user']);
            $userRoleId = $stmt->fetchColumn();

            // Add roleId to User table without foreign key
            $db->exec("
                ALTER TABLE `User` 
                ADD COLUMN `roleId` INT NULL
            ");

            // Update users in smaller batches
            $updateUser = $db->prepare("
                UPDATE `User` 
                SET `roleId` = ? 
                WHERE `roleId` IS NULL 
                LIMIT 1000
            ");

            do {
                $updateUser->execute([$userRoleId]);
                $affected = $updateUser->rowCount();
            } while ($affected > 0);

            // Make roleId NOT NULL
            $db->exec("
                ALTER TABLE `User` 
                MODIFY COLUMN `roleId` INT NOT NULL
            ");

            // Add foreign key constraint
            $db->exec("
                ALTER TABLE `User` 
                ADD CONSTRAINT `fk_user_role` 
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
            ");

        } catch (\Exception $e) {
            // Reset settings
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }

        // Reset settings
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
        $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
    }

    public function down($db) {
        try {
            // Set a longer lock timeout
            $db->exec("SET innodb_lock_wait_timeout = 50");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

            // Remove foreign key constraint
            $db->exec("
                ALTER TABLE `User` 
                DROP FOREIGN KEY `fk_user_role`
            ");

            // Remove roleId column
            $db->exec("
                ALTER TABLE `User` 
                DROP COLUMN `roleId`
            ");

            // Drop Role table
            $db->exec("DROP TABLE IF EXISTS `Role`");

        } catch (\Exception $e) {
            // Reset settings
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            throw $e;
        }

        // Reset settings
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
        $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
    }
} 