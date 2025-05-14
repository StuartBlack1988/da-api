<?php

namespace DietitianAssist\Practice;

use PDO;
use DietitianAssist\Core\ApiResponse;
use DietitianAssist\Core\Validation;
use DietitianAssist\Core\Security;

class PracticeController {
    private $db;
    private $validation;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->validation = new Validation();
    }

    public function registerPractice($data) {
        try {
            // Validate required fields
            $requiredFields = [
                'practiceName',
                'email',
                'name',
                'surname',
                'phoneNumber',
                'billingLine1',
                'billingLine2',
                'billingSuburb',
                'billingTown',
                'billingCountry',
                'billingPostalCode'
            ];

            if (!$this->validation->validateRequiredFields($data, $requiredFields)) {
                return ApiResponse::error('Missing required fields', 400);
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // 1. Insert into Practice table
                $practiceSql = "INSERT INTO Practice (
                    practiceName, phoneNumber, email, 
                    billingLine1, billingLine2, billingLine3, 
                    billingSuburb, billingTown, billingCountry, 
                    billingPostalCode, practiceNumber, vatNumber
                ) VALUES (
                    :practiceName, :phoneNumber, :email,
                    :billingLine1, :billingLine2, :billingLine3,
                    :billingSuburb, :billingTown, :billingCountry,
                    :billingPostalCode, :practiceNumber, :vatNumber
                )";

                $practiceStmt = $this->db->prepare($practiceSql);
                $practiceStmt->execute([
                    'practiceName' => $data['practiceName'],
                    'phoneNumber' => $data['phoneNumber'],
                    'email' => $data['email'],
                    'billingLine1' => $data['billingLine1'],
                    'billingLine2' => $data['billingLine2'],
                    'billingLine3' => $data['billingLine3'] ?? null,
                    'billingSuburb' => $data['billingSuburb'],
                    'billingTown' => $data['billingTown'],
                    'billingCountry' => $data['billingCountry'],
                    'billingPostalCode' => $data['billingPostalCode'],
                    'practiceNumber' => $data['practiceNumber'] ?? null,
                    'vatNumber' => $data['vatNumber'] ?? null
                ]);

                $practiceId = $this->db->lastInsertId();

                // 2. Insert into User table
                $userSql = "INSERT INTO User (
                    email, name, surname, userStatusId
                ) VALUES (
                    :email, :name, :surname,
                    (SELECT userStatusId FROM UserStatus WHERE name = 'pending')
                )";

                $userStmt = $this->db->prepare($userSql);
                $userStmt->execute([
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'surname' => $data['surname']
                ]);

                $userId = $this->db->lastInsertId();

                // 3. Insert into DietitianDetails table
                $dietitianSql = "INSERT INTO DietitianDetails (
                    professionalRegistration,
                    featurePracticeBilling,
                    featurePracticeBookings,
                    featureMealPlanTemplates,
                    featureInvoiceTemplates
                ) VALUES (
                    :professionalRegistration,
                    :featurePracticeBilling,
                    :featurePracticeBookings,
                    :featureMealPlanTemplates,
                    :featureInvoiceTemplates
                )";

                $dietitianStmt = $this->db->prepare($dietitianSql);
                $dietitianStmt->execute([
                    'professionalRegistration' => $data['professionalRegistration'] ?? null,
                    'featurePracticeBilling' => $data['featurePracticeBilling'] ?? false,
                    'featurePracticeBookings' => $data['featurePracticeBookings'] ?? false,
                    'featureMealPlanTemplates' => $data['featureMealPlanTemplates'] ?? false,
                    'featureInvoiceTemplates' => $data['featureInvoiceTemplates'] ?? false
                ]);

                $dietitianDetailsId = $this->db->lastInsertId();

                // 4. Insert into PracticeUser table
                $practiceUserSql = "INSERT INTO PracticeUser (
                    practiceId, userId, roleId, primaryDietitian, dietitianDetailsId, status
                ) VALUES (
                    :practiceId, :userId,
                    (SELECT roleId FROM Role WHERE name = 'dietitian'),
                    TRUE, :dietitianDetailsId, 'active'
                )";

                $practiceUserStmt = $this->db->prepare($practiceUserSql);
                $practiceUserStmt->execute([
                    'practiceId' => $practiceId,
                    'userId' => $userId,
                    'dietitianDetailsId' => $dietitianDetailsId
                ]);

                // 5. Generate set-password token
                $token = Security::generateToken();
                $tokenSql = "INSERT INTO Token (
                    token, userId, tokenType, expiryDateTime
                ) VALUES (
                    :token, :userId, 'set-password',
                    DATE_ADD(NOW(), INTERVAL 24 HOUR)
                )";

                $tokenStmt = $this->db->prepare($tokenSql);
                $tokenStmt->execute([
                    'token' => $token,
                    'userId' => $userId
                ]);

                // Commit transaction
                $this->db->commit();

                return ApiResponse::success(['message' => 'Practice registered successfully']);

            } catch (\Exception $e) {
                // Rollback transaction on error
                $this->db->rollBack();
                error_log("Practice registration error: " . $e->getMessage());
                return ApiResponse::error('Failed to register practice: ' . $e->getMessage(), 500);
            }

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to register practice: ' . $e->getMessage());
        }
    }
} 