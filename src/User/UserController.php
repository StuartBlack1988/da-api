<?php

namespace DietitianAssist\User;

use PDO;
use PDOException;

class UserController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function listUsers($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $params = [];
            $whereClauses = [];

            // Build WHERE clause based on filters
            if (isset($filters['role'])) {
                $whereClauses[] = "r.name = ?";
                $params[] = $filters['role'];
            }

            if (isset($filters['isActive'])) {
                $whereClauses[] = "u.isActive = ?";
                $params[] = $filters['isActive'] ? 1 : 0;
            }

            if (isset($filters['search'])) {
                $whereClauses[] = "(u.name LIKE ? OR u.surname LIKE ? OR u.email LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            // Build the base query
            $sql = "
                SELECT 
                    u.*,
                    r.name as role
                FROM User u
                JOIN Role r ON u.roleId = r.roleId
            ";

            // Add WHERE clause if filters exist
            if (!empty($whereClauses)) {
                $sql .= " WHERE " . implode(" AND ", $whereClauses);
            }

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) FROM User u JOIN Role r ON u.roleId = r.roleId";
            if (!empty($whereClauses)) {
                $countSql .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Add pagination
            $sql .= " ORDER BY u.createdDate DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            // Execute the main query
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'users' => $users,
                    'pagination' => [
                        'total' => (int)$total,
                        'page' => (int)$page,
                        'limit' => (int)$limit,
                        'totalPages' => ceil($total / $limit)
                    ]
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error listing users: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => 'Failed to list users'
            ];
        }
    }

    public function updateUser($userId, $data) {
        try {
            // Check if user exists
            $checkStmt = $this->db->prepare("SELECT * FROM User WHERE userId = ?");
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();

            if (!$user) {
                return [
                    'status' => 'error',
                    'error' => 'User not found'
                ];
            }

            // Check if email is already in use by another user
            if (isset($data['email']) && $data['email'] !== $user['email']) {
                $emailStmt = $this->db->prepare("SELECT userId FROM User WHERE email = ? AND userId != ?");
                $emailStmt->execute([$data['email'], $userId]);
                if ($emailStmt->fetch()) {
                    return [
                        'status' => 'error',
                        'error' => 'Email already in use'
                    ];
                }
            }

            // Check if role exists if roleId is provided
            if (isset($data['roleId'])) {
                $roleStmt = $this->db->prepare("SELECT roleId FROM Role WHERE roleId = ?");
                $roleStmt->execute([$data['roleId']]);
                if (!$roleStmt->fetch()) {
                    return [
                        'status' => 'error',
                        'error' => 'Invalid role ID'
                    ];
                }
            }

            // Build update query
            $updateFields = [];
            $params = [];

            $allowedFields = ['email', 'name', 'surname', 'roleId'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updateFields)) {
                return [
                    'status' => 'error',
                    'error' => 'No valid fields to update'
                ];
            }

            $params[] = $userId;
            $sql = "UPDATE User SET " . implode(", ", $updateFields) . " WHERE userId = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Get updated user data
            $updatedStmt = $this->db->prepare("
                SELECT u.*, r.name as role 
                FROM User u 
                JOIN Role r ON u.roleId = r.roleId 
                WHERE u.userId = ?
            ");
            $updatedStmt->execute([$userId]);
            $updatedUser = $updatedStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $updatedUser
            ];
        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => 'Failed to update user'
            ];
        }
    }

    public function deactivateUser($userId) {
        try {
            // Check if user exists
            $checkStmt = $this->db->prepare("SELECT * FROM User WHERE userId = ?");
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();

            if (!$user) {
                return [
                    'status' => 'error',
                    'error' => 'User not found'
                ];
            }

            // Update user status
            $stmt = $this->db->prepare("
                UPDATE User 
                SET isActive = FALSE,
                    deactivatedAt = CURRENT_TIMESTAMP
                WHERE userId = ?
            ");
            
            $stmt->execute([$userId]);

            return [
                'status' => 'success',
                'message' => 'User deactivated successfully',
                'data' => [
                    'userId' => $userId,
                    'deactivatedAt' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error deactivating user: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => 'Failed to deactivate user'
            ];
        }
    }

    public function reactivateUser($userId) {
        try {
            // Check if user exists
            $checkStmt = $this->db->prepare("SELECT * FROM User WHERE userId = ?");
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();

            if (!$user) {
                return [
                    'status' => 'error',
                    'error' => 'User not found'
                ];
            }

            // Update user status
            $stmt = $this->db->prepare("
                UPDATE User 
                SET isActive = TRUE,
                    deactivatedAt = NULL
                WHERE userId = ?
            ");
            
            $stmt->execute([$userId]);

            return [
                'status' => 'success',
                'message' => 'User reactivated successfully',
                'data' => [
                    'userId' => $userId,
                    'deactivatedAt' => null
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error reactivating user: " . $e->getMessage());
            return [
                'status' => 'error',
                'error' => 'Failed to reactivate user'
            ];
        }
    }
} 