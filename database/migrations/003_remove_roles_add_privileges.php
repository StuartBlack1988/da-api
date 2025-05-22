<?php

namespace DietitianAssist\Migration;

class RemoveRolesAddPrivileges003 {
    public function up($db) {
        // Create Privileges table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Privileges` (
                `privilegeId` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_privilege_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add privilegeId to ReceptionistDetails
        $db->exec("
            ALTER TABLE `ReceptionistDetails`
            ADD COLUMN `privilegeId` INT NULL,
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`)
        ");

        // Add privilegeId to PracticeManagerDetails
        $db->exec("
            ALTER TABLE `PracticeManagerDetails`
            ADD COLUMN `privilegeId` INT NULL,
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`)
        ");

        // Add privilegeId to DietitianDetails
        $db->exec("
            ALTER TABLE `DietitianDetails`
            ADD COLUMN `privilegeId` INT NULL,
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`)
        ");

        // Add privilegeId to PatientDetails
        $db->exec("
            ALTER TABLE `PatientDetails`
            ADD COLUMN `privilegeId` INT NULL,
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`)
        ");

        // Insert default privileges
        $db->exec("
            INSERT INTO `Privileges` (`name`, `description`) VALUES
            ('make-bookings', 'Ability to create and manage bookings'),
            ('view-practice-bookings', 'Ability to view practice bookings'),
            ('view-practice-invoices', 'Ability to view practice invoices')
        ");

        // Remove roleId from PracticeUser
        $db->exec("
            ALTER TABLE `PracticeUser`
            DROP FOREIGN KEY `PracticeUser_ibfk_3`,
            DROP COLUMN `roleId`
        ");

        // Drop Role table
        $db->exec("DROP TABLE IF EXISTS `Role`");
    }

    public function down($db) {
        // Recreate Role table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Role` (
                `roleId` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_role_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Add back roleId to PracticeUser
        $db->exec("
            ALTER TABLE `PracticeUser`
            ADD COLUMN `roleId` INT NOT NULL,
            ADD FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`)
        ");

        // Remove privilegeId from all details tables
        $db->exec("
            ALTER TABLE `ReceptionistDetails` DROP FOREIGN KEY `ReceptionistDetails_ibfk_1`, DROP COLUMN `privilegeId`;
            ALTER TABLE `PracticeManagerDetails` DROP FOREIGN KEY `PracticeManagerDetails_ibfk_1`, DROP COLUMN `privilegeId`;
            ALTER TABLE `DietitianDetails` DROP FOREIGN KEY `DietitianDetails_ibfk_1`, DROP COLUMN `privilegeId`;
            ALTER TABLE `PatientDetails` DROP FOREIGN KEY `PatientDetails_ibfk_1`, DROP COLUMN `privilegeId`
        ");

        // Drop Privileges table
        $db->exec("DROP TABLE IF EXISTS `Privileges`");

        // Insert default roles
        $db->exec("
            INSERT INTO `Role` (`name`, `description`) VALUES
            ('admin', 'System administrator'),
            ('practice_manager', 'Practice manager'),
            ('dietitian', 'Dietitian'),
            ('receptionist', 'Receptionist'),
            ('client', 'Client/Patient')
        ");
    }
} 