<?php

namespace DietitianAssist\ApiAuth;

use PDO;
use PDOException;

class ApiAuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createApiToken($name, $description = null, $expiryDate = null, $createdBy = null) {
        try {
            $token = bin2hex(random_bytes(32));
            
            $stmt = $this->db->prepare("
                INSERT INTO ApiAuth (
                    name, 
                    token, 
                    description, 
                    expiryDate, 
                    createdBy,
                    lastUsed,
                    isActive
                ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, TRUE)
            ");
            
            $stmt->execute([
                $name,
                $token,
                $description,
                $expiryDate,
                $createdBy
            ]);
            
            return [
                'status' => 'success',
                'message' => 'API token created successfully',
                'token' => $token
            ];
        } catch (PDOException $e) {
            error_log("Error creating API token: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to create API token'
            ];
        }
    }

    public function validateApiToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM ApiAuth 
                WHERE token = ? 
                AND isActive = TRUE 
                AND (expiryDate IS NULL OR expiryDate > CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([$token]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return false;
            }
            
            // Update last used timestamp
            $updateStmt = $this->db->prepare("
                UPDATE ApiAuth 
                SET lastUsed = CURRENT_TIMESTAMP
                WHERE apiAuthId = ?
            ");
            
            $updateStmt->execute([$result['apiAuthId']]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Error validating API token: " . $e->getMessage());
            return false;
        }
    }

    public function deactivateApiToken($apiAuthId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE ApiAuth 
                SET isActive = FALSE,
                    deactivatedAt = CURRENT_TIMESTAMP
                WHERE apiAuthId = ?
            ");
            
            $stmt->execute([$apiAuthId]);
            
            return [
                'status' => 'success',
                'message' => 'API token deactivated successfully'
            ];
        } catch (PDOException $e) {
            error_log("Error deactivating API token: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to deactivate API token'
            ];
        }
    }

    public function listApiTokens($userId = null) {
        try {
            $sql = "
                SELECT 
                    a.*,
                    u.email as createdByEmail
                FROM ApiAuth a
                LEFT JOIN User u ON a.createdBy = u.userId
            ";
            
            $params = [];
            
            if ($userId) {
                $sql .= " WHERE a.createdBy = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY a.createdDate DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'status' => 'success',
                'tokens' => $stmt->fetchAll()
            ];
        } catch (PDOException $e) {
            error_log("Error listing API tokens: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to list API tokens'
            ];
        }
    }
} 