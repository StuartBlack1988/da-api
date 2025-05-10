<?php

class InitialSchema {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function up() {
        // Create Role table first (no dependencies)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `Role` (
                roleId INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                createdDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Insert default roles
        $this->pdo->exec("
            INSERT INTO `Role` (name, description) VALUES
            ('super', 'Super administrator with full access'),
            ('pending-patient', 'Patient who has registered but not set password'),
            ('pending-client', 'Client who has registered but not set password'),
            ('client', 'Registered client with password set'),
            ('patient', 'Registered patient with password set')
        ");

        // Create User table (depends on Role)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `User` (
                userId INT(11) AUTO_INCREMENT PRIMARY KEY,
                password VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,
                email VARCHAR(75) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                name VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                surname VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
                createdDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                modifiedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                lastLogin DATETIME NULL,
                roleId INT NOT NULL,
                clientId INT NULL,
                FOREIGN KEY (roleId) REFERENCES `Role`(roleId),
                FOREIGN KEY (clientId) REFERENCES `User`(userId) ON DELETE RESTRICT ON UPDATE CASCADE,
                INDEX (email),
                INDEX idx_user_client (clientId)
            )
        ");

        // Create Token table (depends on User)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `Token` (
                tokenId INT AUTO_INCREMENT PRIMARY KEY,
                token VARCHAR(255) NOT NULL,
                userId INT NOT NULL,
                tokenType ENUM('register', 'set-password', 'login') NOT NULL,
                expiryDateTime TIMESTAMP NOT NULL,
                createdDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                isUsed BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (userId) REFERENCES `User`(userId) ON DELETE CASCADE
            )
        ");

        // Create ApiAuth table (depends on User)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `ApiAuth` (
                apiAuthId INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                description TEXT,
                isActive BOOLEAN DEFAULT TRUE,
                lastUsed TIMESTAMP NULL,
                createdDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expiryDate TIMESTAMP NULL,
                createdBy INT,
                FOREIGN KEY (createdBy) REFERENCES `User`(userId),
                INDEX (token)
            )
        ");

        // Create trigger for ApiAuth token generation
        $this->pdo->exec("
            DELIMITER //
            CREATE TRIGGER before_api_auth_insert 
            BEFORE INSERT ON `ApiAuth`
            FOR EACH ROW
            BEGIN
                IF NEW.token IS NULL THEN
                    SET NEW.token = CONCAT(
                        SUBSTRING(MD5(RAND()), 1, 8), '-',
                        SUBSTRING(MD5(RAND()), 1, 4), '-',
                        SUBSTRING(MD5(RAND()), 1, 4), '-',
                        SUBSTRING(MD5(RAND()), 1, 4), '-',
                        SUBSTRING(MD5(RAND()), 1, 12)
                    );
                END IF;
            END//
            DELIMITER ;
        ");

        // Create UserDetails table (depends on User)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `UserDetails` (
                userDetailsId INT PRIMARY KEY AUTO_INCREMENT,
                userId INT NOT NULL,
                cellNumber VARCHAR(20),
                addressLine1 VARCHAR(100),
                addressLine2 VARCHAR(100),
                addressLine3 VARCHAR(100),
                addressSuburb VARCHAR(100),
                addressTown VARCHAR(100),
                addressCountry VARCHAR(100),
                addressPostalCode VARCHAR(20),
                billingLine1 VARCHAR(100),
                billingLine2 VARCHAR(100),
                billingLine3 VARCHAR(100),
                billingSuburb VARCHAR(100),
                billingTown VARCHAR(100),
                billingCountry VARCHAR(100),
                billingPostalCode VARCHAR(20),
                practiceName VARCHAR(100),
                professionalRegistration VARCHAR(50),
                vatNumber VARCHAR(50),
                dependantCode VARCHAR(50),
                idNumber VARCHAR(20),
                dateOfBirth DATE,
                bankAccountNumber VARCHAR(50),
                bankBranchCode VARCHAR(20),
                bankName VARCHAR(100),
                bankAccountHolderName VARCHAR(100),
                bankAccountType VARCHAR(50),
                hasMedicalAid BOOLEAN DEFAULT FALSE,
                createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (userId) REFERENCES `User`(userId) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create Dependent table (depends on User and UserDetails)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `Dependent` (
                dependentId INT PRIMARY KEY AUTO_INCREMENT,
                userId INT NOT NULL,
                userDetailsId INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                surname VARCHAR(100) NOT NULL,
                dateOfBirth DATE NOT NULL,
                cellNumber VARCHAR(20),
                idNumber VARCHAR(20),
                medicalAidScheme VARCHAR(100),
                medicalAidPlan VARCHAR(100),
                dependantCode VARCHAR(50),
                mainMember VARCHAR(100),
                relationship VARCHAR(50) NOT NULL,
                isActive BOOLEAN DEFAULT TRUE,
                createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (userId) REFERENCES `User`(userId) ON DELETE CASCADE,
                FOREIGN KEY (userDetailsId) REFERENCES `UserDetails`(userDetailsId) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create triggers for User clientId validation
        $this->pdo->exec("
            DELIMITER //
            CREATE TRIGGER trg_user_before_insert 
            BEFORE INSERT ON `User`
            FOR EACH ROW
            BEGIN
                IF ((NEW.roleId = 5 OR NEW.roleId = 2) AND NEW.clientId IS NULL) THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'Patients and pending-patients must have a clientId';
                ELSEIF (NEW.roleId != 5 AND NEW.roleId != 2 AND NEW.clientId IS NOT NULL) THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'Only patients and pending-patients can have a clientId';
                END IF;
            END//

            CREATE TRIGGER trg_user_before_update 
            BEFORE UPDATE ON `User`
            FOR EACH ROW
            BEGIN
                IF ((NEW.roleId = 5 OR NEW.roleId = 2) AND NEW.clientId IS NULL) THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'Patients and pending-patients must have a clientId';
                ELSEIF (NEW.roleId != 5 AND NEW.roleId != 2 AND NEW.clientId IS NOT NULL) THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'Only patients and pending-patients can have a clientId';
                END IF;
            END//
            DELIMITER ;
        ");

        // Create default user
        $this->pdo->exec("
            INSERT INTO `User` (email, name, surname, roleId, createdDate, modifiedDate)
            SELECT 'stuart@swbza.co.za', 'Stuart', 'B', r.roleId, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM `Role` r
            WHERE r.name = 'super'
            LIMIT 1
        ");

        // Create API token for default user
        $this->pdo->exec("
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
    }

    public function down() {
        // Drop triggers first
        $this->pdo->exec("DROP TRIGGER IF EXISTS trg_user_before_insert");
        $this->pdo->exec("DROP TRIGGER IF EXISTS trg_user_before_update");
        $this->pdo->exec("DROP TRIGGER IF EXISTS before_api_auth_insert");

        // Drop tables in reverse order of dependencies
        $this->pdo->exec("DROP TABLE IF EXISTS `Dependent`");
        $this->pdo->exec("DROP TABLE IF EXISTS `UserDetails`");
        $this->pdo->exec("DROP TABLE IF EXISTS `ApiAuth`");
        $this->pdo->exec("DROP TABLE IF EXISTS `Token`");
        $this->pdo->exec("DROP TABLE IF EXISTS `User`");
        $this->pdo->exec("DROP TABLE IF EXISTS `Role`");
    }
} 