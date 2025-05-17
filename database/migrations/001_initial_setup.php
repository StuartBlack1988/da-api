<?php

namespace DietitianAssist\Migration;

class InitialSetup001 {
    public function up($db) {
        // Create SchemaVersion table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `SchemaVersion` (
                `versionId` INT AUTO_INCREMENT PRIMARY KEY,
                `version` VARCHAR(50) NOT NULL,
                `appliedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `description` TEXT
            )
        ");

        // Create Role table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Role` (
                `roleId` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_role_name` (`name`)
            )
        ");

        // Create UserStatus table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `UserStatus` (
                `userStatusId` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL UNIQUE,
                `description` TEXT,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_userstatus_name` (`name`)
            )
        ");

        // Create User table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `User` (
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
            )
        ");

        // Create Token table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Token` (
                `tokenId` INT AUTO_INCREMENT PRIMARY KEY,
                `token` VARCHAR(255) NOT NULL,
                `userId` INT NOT NULL,
                `tokenType` ENUM('register', 'set-password', 'login') NOT NULL,
                `expiryDateTime` TIMESTAMP NOT NULL,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `isUsed` BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (`userId`) REFERENCES `User`(`userId`) ON DELETE CASCADE,
                INDEX `idx_token_token` (`token`),
                INDEX `idx_token_user` (`userId`),
                INDEX `idx_token_type` (`tokenType`),
                INDEX `idx_token_expiry` (`expiryDateTime`),
                INDEX `idx_token_used` (`isUsed`)
            )
        ");

        // Create ApiAuth table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ApiAuth` (
                `apiAuthId` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `token` VARCHAR(255) NOT NULL UNIQUE,
                `description` TEXT,
                `isActive` BOOLEAN DEFAULT TRUE,
                `lastUsed` TIMESTAMP NULL,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expiryDate` TIMESTAMP NULL,
                `createdBy` INT,
                FOREIGN KEY (`createdBy`) REFERENCES `User`(`userId`),
                INDEX `idx_apiauth_token` (`token`),
                INDEX `idx_apiauth_active` (`isActive`),
                INDEX `idx_apiauth_expiry` (`expiryDate`),
                INDEX `idx_apiauth_createdby` (`createdBy`)
            )
        ");

        // Create Practice table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `Practice` (
                `practiceId` INT AUTO_INCREMENT PRIMARY KEY,
                `practiceName` VARCHAR(100) NOT NULL,
                `phoneNumber` VARCHAR(20),
                `email` VARCHAR(75),
                `addressLine1` VARCHAR(100),
                `addressLine2` VARCHAR(100),
                `addressLine3` VARCHAR(100),
                `addressSuburb` VARCHAR(100),
                `addressTown` VARCHAR(100),
                `addressCountry` VARCHAR(100),
                `addressPostalCode` VARCHAR(20),
                `billingLine1` VARCHAR(100),
                `billingLine2` VARCHAR(100),
                `billingLine3` VARCHAR(100),
                `billingSuburb` VARCHAR(100),
                `billingTown` VARCHAR(100),
                `billingCountry` VARCHAR(100),
                `billingPostalCode` VARCHAR(20),
                `practiceNumber` VARCHAR(50),
                `vatNumber` VARCHAR(50),
                `bankAccountNumber` VARCHAR(50),
                `bankBranchCode` VARCHAR(20),
                `bankName` VARCHAR(100),
                `bankAccountHolderName` VARCHAR(100),
                `bankAccountType` VARCHAR(50),
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_practice_name` (`practiceName`),
                INDEX `idx_practice_email` (`email`),
                INDEX `idx_practice_number` (`practiceNumber`),
                INDEX `idx_practice_vat` (`vatNumber`),
                INDEX `idx_practice_postal` (`addressPostalCode`, `billingPostalCode`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create ReceptionistDetails table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ReceptionistDetails` (
                `receptionistDetailsId` INT AUTO_INCREMENT PRIMARY KEY,
                `featurePracticeBilling` BOOLEAN DEFAULT FALSE,
                `featurePracticeBookings` BOOLEAN DEFAULT FALSE,
                `featureMealPlanTemplates` BOOLEAN DEFAULT FALSE,
                `featureInvoiceTemplates` BOOLEAN DEFAULT FALSE,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_receptionist_features` (`featurePracticeBilling`, `featurePracticeBookings`, `featureMealPlanTemplates`, `featureInvoiceTemplates`),
                INDEX `idx_receptionist_billing` (`featurePracticeBilling`),
                INDEX `idx_receptionist_bookings` (`featurePracticeBookings`),
                INDEX `idx_receptionist_mealplans` (`featureMealPlanTemplates`),
                INDEX `idx_receptionist_invoices` (`featureInvoiceTemplates`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create PracticeManagerDetails table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `PracticeManagerDetails` (
                `practiceManagerDetailsId` INT AUTO_INCREMENT PRIMARY KEY,
                `featurePracticeBilling` BOOLEAN DEFAULT FALSE,
                `featurePracticeBookings` BOOLEAN DEFAULT FALSE,
                `featureMealPlanTemplates` BOOLEAN DEFAULT FALSE,
                `featureInvoiceTemplates` BOOLEAN DEFAULT FALSE,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_practicemanager_features` (`featurePracticeBilling`, `featurePracticeBookings`, `featureMealPlanTemplates`, `featureInvoiceTemplates`),
                INDEX `idx_practicemanager_billing` (`featurePracticeBilling`),
                INDEX `idx_practicemanager_bookings` (`featurePracticeBookings`),
                INDEX `idx_practicemanager_mealplans` (`featureMealPlanTemplates`),
                INDEX `idx_practicemanager_invoices` (`featureInvoiceTemplates`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create DietitianDetails table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `DietitianDetails` (
                `dietitianDetailsId` INT AUTO_INCREMENT PRIMARY KEY,
                `professionalRegistration` VARCHAR(50),
                `featurePracticeBilling` BOOLEAN DEFAULT FALSE,
                `featurePracticeBookings` BOOLEAN DEFAULT FALSE,
                `featureMealPlanTemplates` BOOLEAN DEFAULT FALSE,
                `featureInvoiceTemplates` BOOLEAN DEFAULT FALSE,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_dietitian_registration` (`professionalRegistration`),
                INDEX `idx_dietitian_features` (`featurePracticeBilling`, `featurePracticeBookings`, `featureMealPlanTemplates`, `featureInvoiceTemplates`),
                INDEX `idx_dietitian_billing` (`featurePracticeBilling`),
                INDEX `idx_dietitian_bookings` (`featurePracticeBookings`),
                INDEX `idx_dietitian_mealplans` (`featureMealPlanTemplates`),
                INDEX `idx_dietitian_invoices` (`featureInvoiceTemplates`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create PracticeUser table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `PracticeUser` (
                `practiceUserId` INT AUTO_INCREMENT PRIMARY KEY,
                `practiceId` INT NOT NULL,
                `userId` INT NOT NULL,
                `roleId` INT NOT NULL,
                `primaryDietitian` BOOLEAN DEFAULT FALSE,
                `receptionistDetailsId` INT NULL,
                `practiceManagerDetailsId` INT NULL,
                `dietitianDetailsId` INT NULL,
                `patientDetailsId` INT NULL,
                `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                `statusChangedDate` TIMESTAMP NULL,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`practiceId`) REFERENCES `Practice`(`practiceId`),
                FOREIGN KEY (`userId`) REFERENCES `User`(`userId`),
                FOREIGN KEY (`roleId`) REFERENCES `Role`(`roleId`),
                FOREIGN KEY (`receptionistDetailsId`) REFERENCES `ReceptionistDetails`(`receptionistDetailsId`),
                FOREIGN KEY (`practiceManagerDetailsId`) REFERENCES `PracticeManagerDetails`(`practiceManagerDetailsId`),
                FOREIGN KEY (`dietitianDetailsId`) REFERENCES `DietitianDetails`(`dietitianDetailsId`),
                FOREIGN KEY (`patientDetailsId`) REFERENCES `PatientDetails`(`patientDetailsId`),
                INDEX `idx_practiceuser_practice` (`practiceId`),
                INDEX `idx_practiceuser_user` (`userId`),
                INDEX `idx_practiceuser_role` (`roleId`),
                INDEX `idx_practiceuser_dietitian` (`primaryDietitian`),
                INDEX `idx_practiceuser_details` (`receptionistDetailsId`, `practiceManagerDetailsId`, `dietitianDetailsId`, `patientDetailsId`),
                INDEX `idx_practiceuser_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create PatientDetails table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `PatientDetails` (
                `patientDetailsId` INT AUTO_INCREMENT PRIMARY KEY,
                `medicalAidScheme` VARCHAR(100),
                `medicalAidPlan` VARCHAR(100),
                `dependantCode` VARCHAR(50),
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_patient_medicalaid` (`medicalAidScheme`, `medicalAidPlan`),
                INDEX `idx_patient_dependant` (`dependantCode`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insert default roles
        $db->exec("
            INSERT INTO `Role` (`name`, `description`) VALUES
            ('admin', 'System administrator'),
            ('practice_manager', 'Practice manager'),
            ('dietitian', 'Dietitian'),
            ('receptionist', 'Receptionist'),
            ('client', 'Client/Patient')
        ");

        // Insert default user statuses
        $db->exec("
            INSERT INTO `UserStatus` (`name`, `description`) VALUES
            ('active', 'Active user'),
            ('inactive', 'Inactive user'),
            ('suspended', 'Suspended user'),
            ('pending', 'Pending verification')
        ");

        // Create default user
        $db->exec("
            INSERT INTO `User` (email, name, surname, createdDate, modifiedDate, userStatusId)
            SELECT 'stuart@swbza.co.za', 'Stuart', 'B', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, us.userStatusId
            FROM `UserStatus` us
            WHERE us.name = 'active'
            LIMIT 1
        ");

        // Create API token for default user
        $db->exec("
            INSERT INTO `ApiAuth` (name, description, isActive, createdDate, createdBy, token)
            SELECT 'Default API Token', 'API token for default user', TRUE, CURRENT_TIMESTAMP, u.userId,
                   CONCAT(
                       SUBSTRING(MD5(RAND()), 1, 8), '-',
                       SUBSTRING(MD5(RAND()), 1, 4), '-',
                       SUBSTRING(MD5(RAND()), 1, 4), '-',
                       SUBSTRING(MD5(RAND()), 1, 4), '-',
                       SUBSTRING(MD5(RAND()), 1, 12)
                   )
            FROM `User` u
            WHERE u.email = 'stuart@swbza.co.za'
            LIMIT 1
        ");

        // Set timezone to South Africa (UTC+2)
        $db->exec("SET time_zone = '+02:00'");

        // Record migration
        $db->exec("
            INSERT INTO `SchemaVersion` (`version`, `description`) 
            VALUES ('001', 'Initial database setup with core tables and relationships')
        ");
    }

    public function down($db) {
        // Drop tables in reverse order
        $db->exec("DROP TABLE IF EXISTS `PatientDetails`");
        $db->exec("DROP TABLE IF EXISTS `PracticeUser`");
        $db->exec("DROP TABLE IF EXISTS `DietitianDetails`");
        $db->exec("DROP TABLE IF EXISTS `PracticeManagerDetails`");
        $db->exec("DROP TABLE IF EXISTS `ReceptionistDetails`");
        $db->exec("DROP TABLE IF EXISTS `Practice`");
        $db->exec("DROP TABLE IF EXISTS `ApiAuth`");
        $db->exec("DROP TABLE IF EXISTS `Token`");
        $db->exec("DROP TABLE IF EXISTS `User`");
        $db->exec("DROP TABLE IF EXISTS `UserStatus`");
        $db->exec("DROP TABLE IF EXISTS `Role`");
        $db->exec("DROP TABLE IF EXISTS `SchemaVersion`");
    }
}