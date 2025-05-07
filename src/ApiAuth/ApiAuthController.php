<?php

namespace App\ApiAuth;

use PDOException;

class ApiAuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createApiToken($name, $description = null, $expiryDate = null, $createdBy = null) {
        error_log("Creating API token with name: " . $name);
        
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
            
            error_log("API token created successfully");
            
            return [
                'status' => 'success',
                'message' => 'API token created successfully',
                'token' => $token
            ];
        } catch (PDOException $e) {
            error_log("Error creating API token: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'status' => 'error',
                'message' => 'Failed to create API token'
            ];
        }
    }

    public function validateApiToken($token) {
        error_log("Validating API token");
        
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
                error_log("Token validation failed: Token not found or expired");
                return false;
            }
            
            // Update last used timestamp
            $updateStmt = $this->db->prepare("
                UPDATE ApiAuth 
                SET lastUsed = CURRENT_TIMESTAMP,
                    usageCount = COALESCE(usageCount, 0) + 1
                WHERE apiAuthId = ?
            ");
            
            $updateStmt->execute([$result['apiAuthId']]);
            error_log("Token validation successful. Usage count updated.");
            
            return true;
        } catch (PDOException $e) {
            error_log("Error validating API token: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function deactivateApiToken($apiAuthId) {
        error_log("Deactivating API token: " . $apiAuthId);
        
        try {
            $stmt = $this->db->prepare("
                UPDATE ApiAuth 
                SET isActive = FALSE,
                    deactivatedAt = CURRENT_TIMESTAMP
                WHERE apiAuthId = ?
            ");
            
            $stmt->execute([$apiAuthId]);
            
            error_log("API token deactivated successfully");
            
            return [
                'status' => 'success',
                'message' => 'API token deactivated successfully'
            ];
        } catch (PDOException $e) {
            error_log("Error deactivating API token: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'status' => 'error',
                'message' => 'Failed to list API tokens'
            ];
        }
    }
} 