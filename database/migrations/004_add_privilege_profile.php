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

        // Get the actual foreign key names
        $fkNames = [];
        $tables = ['ReceptionistDetails', 'PracticeManagerDetails', 'DietitianDetails', 'PatientDetails'];
        
        foreach ($tables as $table) {
            $result = $db->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '$table'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND REFERENCED_TABLE_NAME = 'Privileges'
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($result)) {
                $fkNames[$table] = $result[0]['CONSTRAINT_NAME'];
            }
        }

        // Drop foreign keys if they exist
        foreach ($fkNames as $table => $fkName) {
            $db->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fkName`");
        }

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