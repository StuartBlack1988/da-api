<?php

namespace DietitianAssist\Dependent;

use PDO;
use PDOException;

class DependentController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getDependents($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT d.*, ud.* 
                FROM `Dependent` d
                JOIN `UserDetails` ud ON d.userDetailsId = ud.userDetailsId
                WHERE d.userId = ? AND d.isActive = TRUE
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting dependents: " . $e->getMessage());
            return ['error' => 'Failed to get dependents'];
        }
    }

    public function getDependent($userId, $dependentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT d.*, ud.* 
                FROM `Dependent` d
                JOIN `UserDetails` ud ON d.userDetailsId = ud.userDetailsId
                WHERE d.userId = ? AND d.dependentId = ? AND d.isActive = TRUE
            ");
            $stmt->execute([$userId, $dependentId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting dependent: " . $e->getMessage());
            return ['error' => 'Failed to get dependent'];
        }
    }

    public function createDependent($userId, $data) {
        try {
            $this->db->beginTransaction();

            // First, create UserDetails for the dependent
            $stmt = $this->db->prepare("
                INSERT INTO `UserDetails` (
                    userId, cellNumber, addressLine1, addressLine2, addressLine3,
                    addressSuburb, addressTown, addressCountry, addressPostalCode,
                    billingLine1, billingLine2, billingLine3, billingSuburb,
                    billingTown, billingCountry, billingPostalCode, practiceName,
                    professionalRegistration, vatNumber, dependantCode, idNumber,
                    dateOfBirth, bankAccountNumber, bankBranchCode,
                    bankName, bankAccountHolderName, bankAccountType, hasMedicalAid
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $userId,
                $data['cellNumber'] ?? null,
                $data['addressLine1'] ?? null,
                $data['addressLine2'] ?? null,
                $data['addressLine3'] ?? null,
                $data['addressSuburb'] ?? null,
                $data['addressTown'] ?? null,
                $data['addressCountry'] ?? null,
                $data['addressPostalCode'] ?? null,
                $data['billingLine1'] ?? null,
                $data['billingLine2'] ?? null,
                $data['billingLine3'] ?? null,
                $data['billingSuburb'] ?? null,
                $data['billingTown'] ?? null,
                $data['billingCountry'] ?? null,
                $data['billingPostalCode'] ?? null,
                $data['practiceName'] ?? null,
                $data['professionalRegistration'] ?? null,
                $data['vatNumber'] ?? null,
                $data['dependantCode'] ?? null,
                $data['idNumber'] ?? null,
                $data['dateOfBirth'] ?? null,
                $data['bankAccountNumber'] ?? null,
                $data['bankBranchCode'] ?? null,
                $data['bankName'] ?? null,
                $data['bankAccountHolderName'] ?? null,
                $data['bankAccountType'] ?? null,
                $data['hasMedicalAid'] ?? false
            ]);

            $userDetailsId = $this->db->lastInsertId();

            // Then create the Dependent record
            $stmt = $this->db->prepare("
                INSERT INTO `Dependent` (
                    userId, userDetailsId, name, surname, dateOfBirth,
                    cellNumber, idNumber, medicalAidScheme, medicalAidPlan,
                    dependantCode, mainMember, relationship
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $userDetailsId,
                $data['name'],
                $data['surname'],
                $data['dateOfBirth'],
                $data['cellNumber'] ?? null,
                $data['idNumber'] ?? null,
                $data['medicalAidScheme'] ?? null,
                $data['medicalAidPlan'] ?? null,
                $data['dependantCode'] ?? null,
                $data['mainMember'] ?? null,
                $data['relationship']
            ]);

            $this->db->commit();
            return ['message' => 'Dependent created successfully'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating dependent: " . $e->getMessage());
            return ['error' => 'Failed to create dependent'];
        }
    }

    public function updateDependent($userId, $dependentId, $data) {
        try {
            $this->db->beginTransaction();

            // Update UserDetails
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (!in_array($key, ['name', 'surname', 'relationship', 'cellNumber', 'medicalAidScheme', 'medicalAidPlan', 'mainMember', 'dependentId', 'userId', 'userDetailsId'])) {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }

            if (!empty($fields)) {
                $stmt = $this->db->prepare("
                    UPDATE `UserDetails` ud
                    JOIN `Dependent` d ON ud.userDetailsId = d.userDetailsId
                    SET " . implode(', ', $fields) . "
                    WHERE d.userId = ? AND d.dependentId = ?
                ");
                
                $values[] = $userId;
                $values[] = $dependentId;
                $stmt->execute($values);
            }

            // Update Dependent
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'surname', 'relationship', 'cellNumber', 'medicalAidScheme', 'medicalAidPlan', 'mainMember'])) {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }

            if (!empty($fields)) {
                $stmt = $this->db->prepare("
                    UPDATE `Dependent`
                    SET " . implode(', ', $fields) . "
                    WHERE userId = ? AND dependentId = ?
                ");
                
                $values[] = $userId;
                $values[] = $dependentId;
                $stmt->execute($values);
            }

            $this->db->commit();
            return ['message' => 'Dependent updated successfully'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating dependent: " . $e->getMessage());
            return ['error' => 'Failed to update dependent'];
        }
    }

    public function deactivateDependent($userId, $dependentId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE `Dependent`
                SET isActive = FALSE
                WHERE userId = ? AND dependentId = ?
            ");
            
            $stmt->execute([$userId, $dependentId]);
            return ['message' => 'Dependent deactivated successfully'];
        } catch (PDOException $e) {
            error_log("Error deactivating dependent: " . $e->getMessage());
            return ['error' => 'Failed to deactivate dependent'];
        }
    }
} 