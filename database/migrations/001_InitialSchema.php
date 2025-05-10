<?php

class Migration_001_InitialSchema {
    public function up($db) {
        // Create Role table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Role` (
                `roleId` INT PRIMARY KEY AUTO_INCREMENT,
                `name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT,
                `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Create User table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `User` (
                `userId` INT PRIMARY KEY AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password` VARCHAR(255),
                `name` VARCHAR(100),
                `surname` VARCHAR(100),
                `roleId` INT NOT NULL,
                `clientId` INT,
                `isActive` BOOLEAN DEFAULT TRUE,
                `lastLogin` TIMESTAMP NULL,
                `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`),
                FOREIGN KEY (`clientId`) REFERENCES `User`(`userId`)
            )
        ");

        // Create Token table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Token` (
                `tokenId` INT PRIMARY KEY AUTO_INCREMENT,
                `userId` INT NOT NULL,
                `token` VARCHAR(255) NOT NULL UNIQUE,
                `tokenType` VARCHAR(50) NOT NULL,
                `isUsed` BOOLEAN DEFAULT FALSE,
                `expiryDateTime` TIMESTAMP NOT NULL,
                `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`userId`) REFERENCES `User`(`userId`)
            )
        ");

        // Insert default roles
        $db->exec("
            INSERT INTO `Role` (`name`, `description`) VALUES
            ('admin', 'System administrator'),
            ('pending-patient', 'Patient pending password setup'),
            ('patient', 'Regular patient user'),
            ('pending-client', 'Client pending password setup'),
            ('client', 'Regular client user')
            ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)
        ");
    }

    public function down($db) {
        $db->exec("DROP TABLE IF EXISTS `Token`");
        $db->exec("DROP TABLE IF EXISTS `User`");
        $db->exec("DROP TABLE IF EXISTS `Role`");
    }
} 