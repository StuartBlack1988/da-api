<?php

namespace DietitianAssist\Migration;

use PDO;

class InitialAuthSetup001 {
    private $logCallback;

    public function setLogCallback($callback) {
        $this->logCallback = $callback;
    }

    private function log($message) {
        if ($this->logCallback) {
            call_user_func($this->logCallback, $message);
        }
    }

    public function up($db) {
        try {
            $this->log("Starting migration 001: Initial Auth Setup");

            // Create SchemaVersion table
            $this->log("Creating SchemaVersion table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `SchemaVersion` (
                    `versionId` INT AUTO_INCREMENT PRIMARY KEY,
                    `version` VARCHAR(50) NOT NULL UNIQUE,
                    `description` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_schemaversion_version` (`version`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create APIAuth table
            $this->log("Creating APIAuth table");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `APIAuth` (
                    `apiAuthId` INT AUTO_INCREMENT PRIMARY KEY,
                    `userId` INT NOT NULL,
                    `token` VARCHAR(255) NOT NULL UNIQUE,
                    `refreshToken` VARCHAR(255) NOT NULL UNIQUE,
                    `expiresAt` TIMESTAMP NOT NULL,
                    `refreshExpiresAt` TIMESTAMP NOT NULL,
                    `ipAddress` VARCHAR(45),
                    `userAgent` TEXT,
                    `createdDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `lastUsedDate` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_apiauth_user` (`userId`),
                    INDEX `idx_apiauth_token` (`token`),
                    INDEX `idx_apiauth_refresh` (`refreshToken`),
                    INDEX `idx_apiauth_expires` (`expiresAt`),
                    INDEX `idx_apiauth_refresh_expires` (`refreshExpiresAt`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Insert default API auth
            $this->log("Inserting default API auth");
            $stmt = $db->prepare("
                INSERT INTO `APIAuth` (
                    `userId`,
                    `token`,
                    `refreshToken`,
                    `expiresAt`,
                    `refreshExpiresAt`,
                    `ipAddress`,
                    `userAgent`
                ) VALUES (
                    :userId,
                    :token,
                    :refreshToken,
                    DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    DATE_ADD(NOW(), INTERVAL 30 DAY),
                    :ipAddress,
                    :userAgent
                )
            ");

            $stmt->execute([
                'userId' => 1, // Default user ID
                'token' => bin2hex(random_bytes(32)), // Generate random token
                'refreshToken' => bin2hex(random_bytes(32)), // Generate random refresh token
                'ipAddress' => '127.0.0.1',
                'userAgent' => 'Migration/1.0'
            ]);

            // Record the migration
            $this->log("Recording migration in SchemaVersion");
            $stmt = $db->prepare("
                INSERT INTO SchemaVersion (version, description) 
                VALUES (?, ?)
            ");
            $stmt->execute(['001', 'Initial auth setup with SchemaVersion and APIAuth tables']);

            $this->log("Migration 001 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function down($db) {
        try {
            $this->log("Starting rollback of migration 001: Initial Auth Setup");

            // Drop APIAuth table
            $this->log("Dropping APIAuth table");
            $db->exec("DROP TABLE IF EXISTS `APIAuth`");

            // Drop SchemaVersion table
            $this->log("Dropping SchemaVersion table");
            $db->exec("DROP TABLE IF EXISTS `SchemaVersion`");

            $this->log("Rollback of migration 001 completed successfully");
        } catch (\Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
} 