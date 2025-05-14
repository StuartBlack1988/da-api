<?php

namespace DietitianAssist\Migration;

class PracticeVerification {
    public function up($db) {
        $db->beginTransaction();
        
        try {
            // Add verification fields to Practice table
            $db->exec("
                ALTER TABLE `Practice`
                ADD COLUMN `isVerified` BOOLEAN DEFAULT FALSE,
                ADD COLUMN `verificationToken` VARCHAR(255) NULL,
                ADD COLUMN `verificationExpiry` TIMESTAMP NULL,
                ADD INDEX `idx_practice_verification` (`isVerified`, `verificationToken`)
            ");

            // Remove primaryDietitian field from PracticeUser table
            $db->exec("
                ALTER TABLE `PracticeUser`
                DROP COLUMN `primaryDietitian`
            ");

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function down($db) {
        $db->beginTransaction();
        
        try {
            // Remove verification fields from Practice table
            $db->exec("
                ALTER TABLE `Practice`
                DROP COLUMN `isVerified`,
                DROP COLUMN `verificationToken`,
                DROP COLUMN `verificationExpiry`
            ");

            // Add back primaryDietitian field to PracticeUser table
            $db->exec("
                ALTER TABLE `PracticeUser`
                ADD COLUMN `primaryDietitian` BOOLEAN DEFAULT FALSE
            ");

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
} 