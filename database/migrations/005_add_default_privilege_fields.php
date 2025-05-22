<?php

namespace DietitianAssist\Migration;

class AddDefaultPrivilegeFields005 {
    public function up($db) {
        // Add default privilege fields to Privileges table
        $db->exec("
            ALTER TABLE `Privileges`
            ADD COLUMN `isPracticeManagerDefault` BOOLEAN DEFAULT FALSE,
            ADD COLUMN `isReceptionistDefault` BOOLEAN DEFAULT FALSE,
            ADD COLUMN `isDietitianDefault` BOOLEAN DEFAULT FALSE,
            ADD COLUMN `isPatientDefault` BOOLEAN DEFAULT FALSE,
            ADD INDEX `idx_privilege_defaults` (`isPracticeManagerDefault`, `isReceptionistDefault`, `isDietitianDefault`, `isPatientDefault`)
        ");

        // Update existing privileges with default values
        $db->exec("
            UPDATE `Privileges` SET
                `isPracticeManagerDefault` = TRUE,
                `isReceptionistDefault` = TRUE,
                `isDietitianDefault` = TRUE,
                `isPatientDefault` = FALSE
            WHERE `name` = 'view-practice-bookings'
        ");

        $db->exec("
            UPDATE `Privileges` SET
                `isPracticeManagerDefault` = TRUE,
                `isReceptionistDefault` = TRUE,
                `isDietitianDefault` = TRUE,
                `isPatientDefault` = FALSE
            WHERE `name` = 'view-practice-invoices'
        ");

        $db->exec("
            UPDATE `Privileges` SET
                `isPracticeManagerDefault` = TRUE,
                `isReceptionistDefault` = TRUE,
                `isDietitianDefault` = TRUE,
                `isPatientDefault` = FALSE
            WHERE `name` = 'make-bookings'
        ");
    }

    public function down($db) {
        // Remove default privilege fields from Privileges table
        $db->exec("
            ALTER TABLE `Privileges`
            DROP INDEX `idx_privilege_defaults`,
            DROP COLUMN `isPracticeManagerDefault`,
            DROP COLUMN `isReceptionistDefault`,
            DROP COLUMN `isDietitianDefault`,
            DROP COLUMN `isPatientDefault`
        ");
    }
} 