<?php

namespace DietitianAssist\Migration;

class AddRoleTable006 {
    public function up($db) {
        // Set a longer lock timeout
        $db->exec("SET innodb_lock_wait_timeout = 50");

        try {
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

            // Insert default roles
            $db->exec("
                INSERT INTO `Role` (`name`, `description`) VALUES
                ('user', 'Regular user with standard permissions'),
                ('super', 'Super user with elevated permissions')
            ");

            // Get the user role ID
            $stmt = $db->query("SELECT roleId FROM Role WHERE name = 'user'");
            $userRoleId = $stmt->fetchColumn();

            // Add roleId to User table without foreign key first
            $db->exec("
                ALTER TABLE `User` 
                ADD COLUMN `roleId` INT NULL
            ");

            // Set default role for existing users
            $db->exec("
                UPDATE `User` 
                SET `roleId` = ?
                WHERE `roleId` IS NULL
            ", [$userRoleId]);

            // Make roleId NOT NULL after setting defaults
            $db->exec("
                ALTER TABLE `User` 
                MODIFY COLUMN `roleId` INT NOT NULL
            ");

            // Add foreign key constraint last
            $db->exec("
                ALTER TABLE `User` 
                ADD CONSTRAINT `fk_user_role` 
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
            ");

        } catch (\Exception $e) {
            // Reset lock timeout
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            throw $e;
        }

        // Reset lock timeout
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
    }

    public function down($db) {
        // Set a longer lock timeout
        $db->exec("SET innodb_lock_wait_timeout = 50");

        try {
            // Remove foreign key constraint first
            $db->exec("
                ALTER TABLE `User` 
                DROP FOREIGN KEY `fk_user_role`
            ");

            // Remove roleId column
            $db->exec("
                ALTER TABLE `User` 
                DROP COLUMN `roleId`
            ");

            // Drop Role table last
            $db->exec("DROP TABLE IF EXISTS `Role`");

        } catch (\Exception $e) {
            // Reset lock timeout
            $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
            throw $e;
        }

        // Reset lock timeout
        $db->exec("SET innodb_lock_wait_timeout = DEFAULT");
    }
} 