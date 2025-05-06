<?php

namespace App\Token;

class TokenController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createToken($userId, $tokenType, $expiryHours = 24) {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Calculate expiry datetime
        $expiryDateTime = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
        
        // Store token
        $stmt = $this->db->prepare("
            INSERT INTO `Token` (token, userId, tokenType, expiryDateTime)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$token, $userId, $tokenType, $expiryDateTime]);
        
        return $token;
    }

    public function validateToken($token, $tokenType) {
        $stmt = $this->db->prepare("
            SELECT t.*, u.email 
            FROM `Token` t
            JOIN `User` u ON t.userId = u.userId
            WHERE t.token = ? 
            AND t.tokenType = ?
            AND t.expiryDateTime > CURRENT_TIMESTAMP
            AND t.isUsed = FALSE
        ");
        
        $stmt->execute([$token, $tokenType]);
        $tokenData = $stmt->fetch();
        
        if (!$tokenData) {
            return null;
        }
        
        return $tokenData;
    }

    public function markTokenAsUsed($token) {
        $stmt = $this->db->prepare("
            UPDATE `Token` 
            SET isUsed = TRUE 
            WHERE token = ?
        ");
        
        return $stmt->execute([$token]);
    }

    public function cleanupExpiredTokens() {
        $stmt = $this->db->prepare("
            DELETE FROM `Token` 
            WHERE expiryDateTime < CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute();
    }
} 