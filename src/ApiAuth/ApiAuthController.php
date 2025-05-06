<?php

namespace App\ApiAuth;

use PDOException;

class ApiAuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createApiToken($data, $createdBy) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `ApiAuth` (name, description, expiryDate, createdBy)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['expiryDate'] ?? null,
                $createdBy
            ]);

            $apiAuthId = $this->db->lastInsertId();
            
            // Get the generated token
            $stmt = $this->db->prepare("SELECT token FROM `ApiAuth` WHERE apiAuthId = ?");
            $stmt->execute([$apiAuthId]);
            $result = $stmt->fetch();

            return [
                'message' => 'API token created successfully',
                'token' => $result['token']
            ];
        } catch (PDOException $e) {
            error_log("Error creating API token: " . $e->getMessage());
            return ['error' => 'Failed to create API token'];
        }
    }

    public function validateApiToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM `ApiAuth` 
                WHERE token = ? 
                AND isActive = TRUE 
                AND (expiryDate IS NULL OR expiryDate > CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([$token]);
            $apiAuth = $stmt->fetch();

            if (!$apiAuth) {
                return null;
            }

            // Update last used timestamp
            $stmt = $this->db->prepare("
                UPDATE `ApiAuth` 
                SET lastUsed = CURRENT_TIMESTAMP 
                WHERE apiAuthId = ?
            ");
            $stmt->execute([$apiAuth['apiAuthId']]);

            return $apiAuth;
        } catch (PDOException $e) {
            error_log("Error validating API token: " . $e->getMessage());
            return null;
        }
    }

    public function deactivateApiToken($apiAuthId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE `ApiAuth` 
                SET isActive = FALSE 
                WHERE apiAuthId = ?
            ");
            
            return $stmt->execute([$apiAuthId]);
        } catch (PDOException $e) {
            error_log("Error deactivating API token: " . $e->getMessage());
            return false;
        }
    }

    public function listApiTokens($userId = null) {
        try {
            $sql = "SELECT * FROM `ApiAuth`";
            $params = [];
            
            if ($userId) {
                $sql .= " WHERE createdBy = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY createdDate DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error listing API tokens: " . $e->getMessage());
            return [];
        }
    }
} 