<?php

namespace DietitianAssist\Practice;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DietitianAssist\Core\Database;
use DietitianAssist\Core\ApiResponse;
use DietitianAssist\Core\Validation;
use DietitianAssist\Core\Security;

class PracticeController {
    private $db;
    private $validation;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->validation = new Validation();
    }

    public function registerPractice(Request $request, Response $response): Response {
        try {
            $data = $request->getParsedBody();

            // Validate required fields
            $requiredFields = [
                'practiceName',
                'email',
                'name',
                'surname',
                'phoneNumber'
            ];

            if (!$this->validation->validateRequiredFields($data, $requiredFields)) {
                return ApiResponse::error($response, 'Missing required fields', 400);
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // 1. Create Practice
                $practiceStmt = $this->db->prepare("
                    INSERT INTO Practice (
                        practiceName, phoneNumber, email,
                        billingLine1, billingLine2, billingLine3,
                        billingSuburb, billingTown, billingCountry,
                        billingPostalCode, practiceNumber, vatNumber
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $practiceStmt->execute([
                    $data['practiceName'],
                    $data['phoneNumber'],
                    $data['email'],
                    $data['billingLine1'] ?? null,
                    $data['billingLine2'] ?? null,
                    $data['billingLine3'] ?? null,
                    $data['billingSuburb'] ?? null,
                    $data['billingTown'] ?? null,
                    $data['billingCountry'] ?? null,
                    $data['billingPostalCode'] ?? null,
                    $data['practiceNumber'] ?? null,
                    $data['vatNumber'] ?? null
                ]);

                $practiceId = $this->db->lastInsertId();

                // 2. Create User
                $userStmt = $this->db->prepare("
                    INSERT INTO User (
                        email, name, surname, userStatusId
                    ) VALUES (?, ?, ?, (
                        SELECT userStatusId FROM UserStatus WHERE name = 'pending'
                    ))
                ");

                $userStmt->execute([
                    $data['email'],
                    $data['name'],
                    $data['surname']
                ]);

                $userId = $this->db->lastInsertId();

                // 3. Create DietitianDetails
                $dietitianStmt = $this->db->prepare("
                    INSERT INTO DietitianDetails (
                        professionalRegistration,
                        featurePracticeBilling,
                        featurePracticeBookings,
                        featureMealPlanTemplates,
                        featureInvoiceTemplates
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                $dietitianStmt->execute([
                    $data['professionalRegistration'] ?? null,
                    $data['featurePracticeBilling'] ?? false,
                    $data['featurePracticeBookings'] ?? false,
                    $data['featureMealPlanTemplates'] ?? false,
                    $data['featureInvoiceTemplates'] ?? false
                ]);

                $dietitianDetailsId = $this->db->lastInsertId();

                // 4. Create PracticeUser
                $practiceUserStmt = $this->db->prepare("
                    INSERT INTO PracticeUser (
                        practiceId, userId, roleId, primaryDietitian,
                        dietitianDetailsId, status
                    ) VALUES (?, ?, (
                        SELECT roleId FROM Role WHERE name = 'dietitian'
                    ), TRUE, ?, 'active')
                ");

                $practiceUserStmt->execute([
                    $practiceId,
                    $userId,
                    $dietitianDetailsId
                ]);

                // 5. Generate and store set-password token
                $token = Security::generateToken();
                $tokenStmt = $this->db->prepare("
                    INSERT INTO Token (
                        token, userId, tokenType, expiryDateTime
                    ) VALUES (?, ?, 'set-password', DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ");

                $tokenStmt->execute([$token, $userId]);

                $this->db->commit();

                return ApiResponse::success($response, [
                    'message' => 'Practice registered successfully',
                    'practiceId' => $practiceId,
                    'userId' => $userId,
                    'setPasswordToken' => $token
                ]);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return ApiResponse::error($response, 'Failed to register practice: ' . $e->getMessage());
        }
    }
} 