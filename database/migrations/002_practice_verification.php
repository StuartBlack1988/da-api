<?php

namespace DietitianAssist\Migration;

class PracticeVerification002 {
    public function up($db) {
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
    }

    public function down($db) {
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
    }
} 