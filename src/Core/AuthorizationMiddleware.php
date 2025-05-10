<?php

namespace DietitianAssist\Core;

class AuthorizationMiddleware {
    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function handle($request, $requiredRole) {
        $token = $request->getHeader('Authorization');
        if (!$token) {
            return false;
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        $stmt = $this->pdo->prepare("
            SELECT u.roleId 
            FROM Token t 
            JOIN User u ON t.userId = u.id 
            WHERE t.token = ? AND t.expiresAt > NOW()
        ");
        $stmt->execute([$token]);
        $userRole = $stmt->fetchColumn();

        if (!$userRole) {
            return false;
        }

        // Check if user has required role
        return $this->hasRequiredRole($userRole, $requiredRole);
    }

    private function hasRequiredRole($userRole, $requiredRole) {
        // Define role hierarchy
        $roleHierarchy = [
            'admin' => ['admin', 'dietitian', 'user'],
            'dietitian' => ['dietitian', 'user'],
            'user' => ['user']
        ];

        return in_array($requiredRole, $roleHierarchy[$userRole] ?? []);
    }
} 