<?php

namespace DietitianAssist\UserDetails;

use PDO;
use PDOException;

class UserDetailsController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getUserDetails($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM `UserDetails` 
                WHERE userId = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting user details: " . $e->getMessage());
            return ['error' => 'Failed to get user details'];
        }
    }

    public function createUserDetails($userId, $data) {
        try {
            $fields = [
                'userId' => $userId,
                'cellNumber' => $data['cellNumber'] ?? null,
                'addressLine1' => $data['addressLine1'] ?? null,
                'addressLine2' => $data['addressLine2'] ?? null,
                'addressLine3' => $data['addressLine3'] ?? null,
                'addressSuburb' => $data['addressSuburb'] ?? null,
                'addressTown' => $data['addressTown'] ?? null,
                'addressCountry' => $data['addressCountry'] ?? null,
                'addressPostalCode' => $data['addressPostalCode'] ?? null,
                'billingLine1' => $data['billingLine1'] ?? null,
                'billingLine2' => $data['billingLine2'] ?? null,
                'billingLine3' => $data['billingLine3'] ?? null,
                'billingSuburb' => $data['billingSuburb'] ?? null,
                'billingTown' => $data['billingTown'] ?? null,
                'billingCountry' => $data['billingCountry'] ?? null,
                'billingPostalCode' => $data['billingPostalCode'] ?? null,
                'practiceName' => $data['practiceName'] ?? null,
                'professionalRegistration' => $data['professionalRegistration'] ?? null,
                'vatNumber' => $data['vatNumber'] ?? null,
                'dependantCode' => $data['dependantCode'] ?? null,
                'idNumber' => $data['idNumber'] ?? null,
                'dateOfBirth' => $data['dateOfBirth'] ?? null,
                'medicalAidScheme' => $data['medicalAidScheme'] ?? null,
                'bankAccountNumber' => $data['bankAccountNumber'] ?? null,
                'bankBranchCode' => $data['bankBranchCode'] ?? null,
                'bankName' => $data['bankName'] ?? null,
                'bankAccountHolderName' => $data['bankAccountHolderName'] ?? null,
                'bankAccountType' => $data['bankAccountType'] ?? null,
                'mainMember' => $data['mainMember'] ?? null,
                'medicalAidPlan' => $data['medicalAidPlan'] ?? null
            ];

            $columns = implode(', ', array_keys($fields));
            $values = implode(', ', array_fill(0, count($fields), '?'));
            
            $stmt = $this->db->prepare("
                INSERT INTO `UserDetails` ($columns)
                VALUES ($values)
            ");
            
            $stmt->execute(array_values($fields));
            return ['message' => 'User details created successfully'];
        } catch (PDOException $e) {
            error_log("Error creating user details: " . $e->getMessage());
            return ['error' => 'Failed to create user details'];
        }
    }

    public function updateUserDetails($userId, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if ($key !== 'userId' && $key !== 'userDetailsId') {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            $values[] = $userId;
            
            $stmt = $this->db->prepare("
                UPDATE `UserDetails` 
                SET " . implode(', ', $fields) . "
                WHERE userId = ?
            ");
            
            $stmt->execute($values);
            return ['message' => 'User details updated successfully'];
        } catch (PDOException $e) {
            error_log("Error updating user details: " . $e->getMessage());
            return ['error' => 'Failed to update user details'];
        }
    }
} 