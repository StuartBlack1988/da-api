<?php

namespace DietitianAssist\Migration;

class AddPrivilegeProfile004 {
    public function up($db) {
        // Create PrivilegeProfile table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `PrivilegeProfile` (
                `privilegeProfileId` INT AUTO_INCREMENT PRIMARY KEY,
                `privilegeId` INT NOT NULL,
                `receptionistDetailsId` INT NULL,
                `practiceManagerDetailsId` INT NULL,
                `dietitianDetailsId` INT NULL,
                `patientDetailsId` INT NULL,
                `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`),
                FOREIGN KEY (`receptionistDetailsId`) REFERENCES `ReceptionistDetails`(`receptionistDetailsId`),
                FOREIGN KEY (`practiceManagerDetailsId`) REFERENCES `PracticeManagerDetails`(`practiceManagerDetailsId`),
                FOREIGN KEY (`dietitianDetailsId`) REFERENCES `DietitianDetails`(`dietitianDetailsId`),
                FOREIGN KEY (`patientDetailsId`) REFERENCES `PatientDetails`(`patientDetailsId`),
                INDEX `idx_privilegeprofile_privilege` (`privilegeId`),
                INDEX `idx_privilegeprofile_details` (`receptionistDetailsId`, `practiceManagerDetailsId`, `dietitianDetailsId`, `patientDetailsId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Remove privilegeId from details tables
        $db->exec("
            ALTER TABLE `ReceptionistDetails` 
            DROP FOREIGN KEY `ReceptionistDetails_ibfk_1`;
        ");

        $db->exec("
            ALTER TABLE `PracticeManagerDetails` 
            DROP FOREIGN KEY `PracticeManagerDetails_ibfk_1`;
        ");

        $db->exec("
            ALTER TABLE `DietitianDetails` 
            DROP FOREIGN KEY `DietitianDetails_ibfk_1`;
        ");

        $db->exec("
            ALTER TABLE `PatientDetails` 
            DROP FOREIGN KEY `PatientDetails_ibfk_1`;
        ");

        // Now drop the columns
        $db->exec("
            ALTER TABLE `ReceptionistDetails` DROP COLUMN `privilegeId`;
            ALTER TABLE `PracticeManagerDetails` DROP COLUMN `privilegeId`;
            ALTER TABLE `DietitianDetails` DROP COLUMN `privilegeId`;
            ALTER TABLE `PatientDetails` DROP COLUMN `privilegeId`;
        ");
    }

    public function down($db) {
        // Add back privilegeId to details tables
        $db->exec("
            ALTER TABLE `ReceptionistDetails` 
            ADD COLUMN `privilegeId` INT NULL;
        ");

        $db->exec("
            ALTER TABLE `PracticeManagerDetails` 
            ADD COLUMN `privilegeId` INT NULL;
        ");

        $db->exec("
            ALTER TABLE `DietitianDetails` 
            ADD COLUMN `privilegeId` INT NULL;
        ");

        $db->exec("
            ALTER TABLE `PatientDetails` 
            ADD COLUMN `privilegeId` INT NULL;
        ");

        // Add foreign keys
        $db->exec("
            ALTER TABLE `ReceptionistDetails` 
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`);
        ");

        $db->exec("
            ALTER TABLE `PracticeManagerDetails` 
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`);
        ");

        $db->exec("
            ALTER TABLE `DietitianDetails` 
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`);
        ");

        $db->exec("
            ALTER TABLE `PatientDetails` 
            ADD FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`);
        ");

        // Drop PrivilegeProfile table
        $db->exec("DROP TABLE IF EXISTS `PrivilegeProfile`");
    }
} 