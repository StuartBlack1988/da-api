<?php

namespace DietitianAssist\Migration;

use PDO;

class CoreTables002 {
    private $logCallback;
    private $startTime;

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        if ($this->logCallback) {
            $elapsed = microtime(true) - $this->startTime;
            call_user_func($this->logCallback, sprintf("[%.2fs] %s", $elapsed, $message));
        }
    }

    private function createAuditTrigger($db, $tableName) {
        try {
            // Set a timeout for this specific operation
            $db->exec("SET SESSION wait_timeout = 5");
            $db->exec("SET SESSION interactive_timeout = 5");
            
            // Check if table exists
            $stmt = $db->query("SHOW TABLES LIKE '{$tableName}'");
            if ($stmt->rowCount() === 0) {
                return;
            }
            
            // Drop existing triggers
            $db->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_insert_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_update_audit");
            $db->exec("DROP TRIGGER IF EXISTS trg_{$tableName}_delete_audit");

            // Get table columns
            $stmt = $db->query("SHOW COLUMNS FROM `{$tableName}`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get primary key column name
            $stmt = $db->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            $primaryKey = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$primaryKey) {
                return;
            }
            $idColumn = $primaryKey['Column_name'];
            
            // Build JSON object pairs for NEW
            $newJsonPairs = [];
            foreach ($columns as $column) {
                $newJsonPairs[] = "'{$column}', NEW.{$column}";
            }
            $newJsonObject = implode(",\n        ", $newJsonPairs);

            // Build JSON object pairs for OLD
            $oldJsonPairs = [];
            foreach ($columns as $column) {
                $oldJsonPairs[] = "'{$column}', OLD.{$column}";
            }
            $oldJsonObject = implode(",\n        ", $oldJsonPairs);

            // Create INSERT trigger
            $db->exec("
                CREATE TRIGGER trg_{$tableName}_insert_audit
                AFTER INSERT ON `{$tableName}`
                FOR EACH ROW
                INSERT INTO `AuditLog` (
                    userId,
                    action,
                    entityType,
                    entityId,
                    newValues,
                    ipAddress,
                    userAgent
                ) VALUES (
                    @current_user_id,
                    'INSERT',
                    '{$tableName}',
                    NEW.{$idColumn},
                    JSON_OBJECT(
                        {$newJsonObject}
                    ),
                    @current_ip_address,
                    @current_user_agent
                )
            ");

            // Create UPDATE trigger
            $db->exec("
                CREATE TRIGGER trg_{$tableName}_update_audit
                AFTER UPDATE ON `{$tableName}`
                FOR EACH ROW
                INSERT INTO `AuditLog` (
                    userId,
                    action,
                    entityType,
                    entityId,
                    oldValues,
                    newValues,
                    ipAddress,
                    userAgent
                ) VALUES (
                    @current_user_id,
                    'UPDATE',
                    '{$tableName}',
                    NEW.{$idColumn},
                    JSON_OBJECT(
                        {$oldJsonObject}
                    ),
                    JSON_OBJECT(
                        {$newJsonObject}
                    ),
                    @current_ip_address,
                    @current_user_agent
                )
            ");

            // Create DELETE trigger
            $db->exec("
                CREATE TRIGGER trg_{$tableName}_delete_audit
                BEFORE DELETE ON `{$tableName}`
                FOR EACH ROW
                INSERT INTO `AuditLog` (
                    userId,
                    action,
                    entityType,
                    entityId,
                    oldValues,
                    ipAddress,
                    userAgent
                ) VALUES (
                    @current_user_id,
                    'DELETE',
                    '{$tableName}',
                    OLD.{$idColumn},
                    JSON_OBJECT(
                        {$oldJsonObject}
                    ),
                    @current_ip_address,
                    @current_user_agent
                )
            ");

        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function up($db) {
        try {
            $this->startTime = microtime(true);
            $this->log("Starting migration 002: Core Tables");

            // Set timeout for this session
            $this->log("Setting session timeout to 30 seconds");
            $db->exec("SET SESSION wait_timeout = 30");
            $db->exec("SET SESSION interactive_timeout = 30");

            // Start transaction
            $this->log("Starting transaction");
            $db->exec("START TRANSACTION");

            // ======================
            // Practice Tables
            // ======================

            // Practice table
            $this->log("Creating Practice table");
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
                    `isVerified` BOOLEAN DEFAULT FALSE,
                    `verificationDate` TIMESTAMP NULL,
                    `verificationNotes` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `modifiedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_practice_name` (`practiceName`),
                    INDEX `idx_practice_email` (`email`),
                    INDEX `idx_practice_number` (`practiceNumber`),
                    INDEX `idx_practice_vat` (`vatNumber`),
                    INDEX `idx_practice_postal` (`addressPostalCode`, `billingPostalCode`),
                    INDEX `idx_practice_verified` (`isVerified`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("Practice table created");

            // ======================
            // Detail Tables
            // ======================

            // ReceptionistDetails table
            $this->log("Creating ReceptionistDetails table");
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
            $this->log("ReceptionistDetails table created");

            // PracticeManagerDetails table
            $this->log("Creating PracticeManagerDetails table");
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
            $this->log("PracticeManagerDetails table created");

            // DietitianDetails table
            $this->log("Creating DietitianDetails table");
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
            $this->log("DietitianDetails table created");

            // PatientDetails table
            $this->log("Creating PatientDetails table");
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
            $this->log("PatientDetails table created");

            // ======================
            // Privilege Tables
            // ======================

            // Privileges table
            $this->log("Creating Privileges table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `Privileges` (
                    `privilegeId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_privilege_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("Privileges table created");

            // UserPrivileges table
            $this->log("Creating UserPrivileges table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `UserPrivileges` (
                    `userPrivilegeId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT NOT NULL,
                    `privilegeId` INT NOT NULL,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`) ON DELETE CASCADE,
                    FOREIGN KEY (`privilegeId`) REFERENCES `Privileges`(`privilegeId`) ON DELETE CASCADE,
                    UNIQUE KEY `idx_user_privilege` (`userId`, `privilegeId`),
                    INDEX `idx_userprivilege_user` (`userId`),
                    INDEX `idx_userprivilege_privilege` (`privilegeId`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("UserPrivileges table created");

            // PrivilegeProfile table
            $this->log("Creating PrivilegeProfile table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `PrivilegeProfile` (
                    `privilegeProfileId` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_privilegeprofile_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("PrivilegeProfile table created");

            // ======================
            // Practice User Table
            // ======================

            // PracticeUser table
            $this->log("Creating PracticeUser table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `PracticeUser` (
                    `practiceUserId` INT AUTO_INCREMENT PRIMARY KEY,
                    `practiceId` INT NOT NULL,
                    `userId` INT NOT NULL,
                    `roleId` INT NOT NULL,
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
                    INDEX `idx_practiceuser_details` (`receptionistDetailsId`, `practiceManagerDetailsId`, `dietitianDetailsId`, `patientDetailsId`),
                    INDEX `idx_practiceuser_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("PracticeUser table created");

            // ======================
            // Audit and API Trace Tables
            // ======================

            // AuditLog table
            $this->log("Creating AuditLog table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `AuditLog` (
                    `auditLogId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT,
                    `action` VARCHAR(50) NOT NULL,
                    `entityType` VARCHAR(50) NOT NULL,
                    `entityId` INT,
                    `oldValues` JSON,
                    `newValues` JSON,
                    `ipAddress` VARCHAR(45),
                    `userAgent` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`),
                    INDEX `idx_auditlog_user` (`userId`),
                    INDEX `idx_auditlog_entity` (`entityType`, `entityId`),
                    INDEX `idx_auditlog_action` (`action`),
                    INDEX `idx_auditlog_created` (`createdDate`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("AuditLog table created");

            // ApiTrace table
            $this->log("Creating ApiTrace table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `ApiTrace` (
                    `apiTraceId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT,
                    `method` VARCHAR(10) NOT NULL,
                    `endpoint` VARCHAR(255) NOT NULL,
                    `requestBody` JSON,
                    `responseBody` JSON,
                    `statusCode` INT,
                    `duration` INT,
                    `ipAddress` VARCHAR(45),
                    `userAgent` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`userId`) REFERENCES `User`(`userId`),
                    INDEX `idx_apitrace_user` (`userId`),
                    INDEX `idx_apitrace_endpoint` (`endpoint`),
                    INDEX `idx_apitrace_method` (`method`),
                    INDEX `idx_apitrace_status` (`statusCode`),
                    INDEX `idx_apitrace_created` (`createdDate`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->log("ApiTrace table created");

            // ======================
            // Create Audit Triggers
            // ======================

            $this->log("Creating audit triggers for all tables");
            
            // List of tables to create triggers for
            $tables = [
                // Core user tables
                'User',
                'UserStatus',
                'Role',
                // Authentication tables
                'Token',
                'ApiAuth',
                // Practice and related tables
                'Practice',
                'ReceptionistDetails',
                'PracticeManagerDetails',
                'DietitianDetails',
                'PatientDetails',
                'Privileges',
                'UserPrivileges',
                'PrivilegeProfile',
                'PracticeUser'
            ];

            // Create triggers for each table
            foreach ($tables as $table) {
                try {
                    $this->createAuditTrigger($db, $table);
                    $this->log("Created triggers for {$table}");
                } catch (\Exception $e) {
                    $this->log("Failed to create triggers for {$table}: " . $e->getMessage());
                    continue;
                }
            }

            // ======================
            // Default Data
            // ======================

            // Create default practice
            $this->log("Creating default practice");
            $stmt = $db->prepare("
                INSERT INTO Practice (practiceName, email, phoneNumber, addressLine1, addressLine2, addressLine3, addressSuburb, addressTown, addressCountry, addressPostalCode)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(['Default Practice', 'practice@example.com', '+27123456789', '123 Main St', 'Suite 100', 'Floor 1', 'CBD', 'Cape Town', 'South Africa', '8001']);
            $this->log("Default practice created");

            // ======================
            // Configuration
            // ======================

            // Set timezone to South Africa (UTC+2)
            $this->log("Setting timezone to South Africa (UTC+2)");
            $stmt = $db->prepare("SET time_zone = ?");
            $stmt->execute(['+02:00']);
            $this->log("Timezone set");

            // Commit transaction
            $this->log("Committing transaction");
            $db->exec("COMMIT");

            $this->log("Migration 002 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            $db->exec("ROLLBACK");
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->startTime = microtime(true);
            $this->log("Starting rollback of migration 002: Core Tables");

            // Set timeout for this session
            $this->log("Setting session timeout to 30 seconds");
            $db->exec("SET SESSION wait_timeout = 30");
            $db->exec("SET SESSION interactive_timeout = 30");

            // Drop tables in reverse order of dependencies
            $tables = [
                'ApiTrace',
                'AuditLog',
                'PracticeUser',
                'Practice'
            ];

            foreach ($tables as $table) {
                $this->log("Dropping table: {$table}");
                $db->exec("DROP TABLE IF EXISTS `{$table}`");
                $this->log("Table {$table} dropped");
            }

            $this->log("Rollback of migration 002 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 