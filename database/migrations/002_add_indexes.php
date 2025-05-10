<?php

class AddIndexes {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function up() {
        // Add indexes to User table
        $this->pdo->exec("
            ALTER TABLE `User`
            ADD INDEX idx_role (roleId),
            ADD INDEX idx_created (createdDate),
            ADD INDEX idx_modified (modifiedDate),
            ADD INDEX idx_last_login (lastLogin)
        ");

        // Add indexes to Token table
        $this->pdo->exec("
            ALTER TABLE `Token`
            ADD INDEX idx_expiry (expiryDateTime),
            ADD INDEX idx_type (tokenType),
            ADD INDEX idx_used (isUsed)
        ");

        // Add indexes to ApiAuth table
        $this->pdo->exec("
            ALTER TABLE `ApiAuth`
            ADD INDEX idx_active (isActive),
            ADD INDEX idx_expiry (expiryDate),
            ADD INDEX idx_last_used (lastUsed),
            ADD INDEX idx_created_by (createdBy)
        ");

        // Add indexes to UserDetails table
        $this->pdo->exec("
            ALTER TABLE `UserDetails`
            ADD INDEX idx_cell (cellNumber),
            ADD INDEX idx_id_number (idNumber),
            ADD INDEX idx_dob (dateOfBirth),
            ADD INDEX idx_medical_aid (hasMedicalAid),
            ADD INDEX idx_created (createdAt),
            ADD INDEX idx_updated (updatedAt)
        ");

        // Add indexes to Dependent table
        $this->pdo->exec("
            ALTER TABLE `Dependent`
            ADD INDEX idx_name (name, surname),
            ADD INDEX idx_dob (dateOfBirth),
            ADD INDEX idx_cell (cellNumber),
            ADD INDEX idx_id_number (idNumber),
            ADD INDEX idx_medical_aid (medicalAidScheme, medicalAidPlan),
            ADD INDEX idx_active (isActive),
            ADD INDEX idx_created (createdAt),
            ADD INDEX idx_updated (updatedAt)
        ");
    }

    public function down() {
        // Remove indexes from Dependent table
        $this->pdo->exec("
            ALTER TABLE `Dependent`
            DROP INDEX idx_name,
            DROP INDEX idx_dob,
            DROP INDEX idx_cell,
            DROP INDEX idx_id_number,
            DROP INDEX idx_medical_aid,
            DROP INDEX idx_active,
            DROP INDEX idx_created,
            DROP INDEX idx_updated
        ");

        // Remove indexes from UserDetails table
        $this->pdo->exec("
            ALTER TABLE `UserDetails`
            DROP INDEX idx_cell,
            DROP INDEX idx_id_number,
            DROP INDEX idx_dob,
            DROP INDEX idx_medical_aid,
            DROP INDEX idx_created,
            DROP INDEX idx_updated
        ");

        // Remove indexes from ApiAuth table
        $this->pdo->exec("
            ALTER TABLE `ApiAuth`
            DROP INDEX idx_active,
            DROP INDEX idx_expiry,
            DROP INDEX idx_last_used,
            DROP INDEX idx_created_by
        ");

        // Remove indexes from Token table
        $this->pdo->exec("
            ALTER TABLE `Token`
            DROP INDEX idx_expiry,
            DROP INDEX idx_type,
            DROP INDEX idx_used
        ");

        // Remove indexes from User table
        $this->pdo->exec("
            ALTER TABLE `User`
            DROP INDEX idx_role,
            DROP INDEX idx_created,
            DROP INDEX idx_modified,
            DROP INDEX idx_last_login
        ");
    }
} 