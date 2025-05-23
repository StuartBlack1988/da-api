<?php

namespace DietitianAssist\Migration;

class AddRoleTable006 {
    public function up($db) {
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

        // Add roleId to User table
        $db->exec("
            ALTER TABLE `User` 
            ADD COLUMN `roleId` INT NULL,
            ADD CONSTRAINT `fk_user_role` 
            FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
        ");

        // Set default role for existing users
        $db->exec("
            UPDATE `User` 
            SET `roleId` = (SELECT `roleId` FROM `Role` WHERE `name` = 'user')
            WHERE `roleId` IS NULL
        ");

        // Make roleId NOT NULL after setting defaults
        $db->exec("
            ALTER TABLE `User` 
            MODIFY COLUMN `roleId` INT NOT NULL
        ");
    }

    public function down($db) {
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
    }
} 