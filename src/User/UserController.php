<?php

namespace DietitianAssist\User;

use DietitianAssist\Core\BaseController;
use DietitianAssist\Core\Response;

class UserController extends BaseController {
    public function __construct(\PDO $pdo) {
        parent::__construct($pdo);
    }

    public function listUsers($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if (!empty($filters['role'])) {
                $where[] = "u.roleId = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['status'])) {
                $where[] = "u.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $where[] = "(u.email LIKE ? OR ud.firstName LIKE ? OR ud.lastName LIKE ?)";
                $search = "%{$filters['search']}%";
                $params = array_merge($params, [$search, $search, $search]);
            }

            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "
                SELECT u.*, ud.firstName, ud.lastName, ud.phone, r.name as roleName
                FROM User u
                LEFT JOIN UserDetails ud ON u.id = ud.userId
                LEFT JOIN Role r ON u.roleId = r.id
                $whereClause
                ORDER BY u.createdAt DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count for pagination
            $countSql = "
                SELECT COUNT(*) 
                FROM User u
                LEFT JOIN UserDetails ud ON u.id = ud.userId
                $whereClause
            ";
            $stmt = $this->pdo->prepare($countSql);
            $stmt->execute(array_slice($params, 0, -2));
            $total = $stmt->fetchColumn();

            return Response::success([
                'users' => $users,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    public function createUser($data) {
        try {
            $errors = $this->validateRequest($data, [
                'email' => 'required|email',
                'password' => 'required|password',
                'roleId' => 'required|integer',
                'firstName' => 'required',
                'lastName' => 'required',
                'phone' => 'required|phone'
            ]);

            if (!empty($errors)) {
                return Response::validationError($errors);
            }

            $this->beginTransaction();

            // Check if email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM User WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $this->rollback();
                return Response::error('Email already exists', 400);
            }

            // Create user
            $stmt = $this->pdo->prepare("
                INSERT INTO User (email, password, roleId, status, createdAt, updatedAt)
                VALUES (?, ?, ?, 'active', NOW(), NOW())
            ");
            $stmt->execute([
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['roleId']
            ]);
            $userId = $this->pdo->lastInsertId();

            // Create user details
            $stmt = $this->pdo->prepare("
                INSERT INTO UserDetails (userId, firstName, lastName, phone, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $userId,
                $data['firstName'],
                $data['lastName'],
                $data['phone']
            ]);

            $this->commit();

            return Response::success(['id' => $userId], 'User created successfully');
        } catch (\Exception $e) {
            $this->rollback();
            return $this->handleError($e);
        }
    }

    public function updateUser($id, $data) {
        try {
            $errors = $this->validateRequest($data, [
                'email' => 'email',
                'password' => 'password',
                'roleId' => 'integer',
                'status' => 'boolean',
                'firstName' => '',
                'lastName' => '',
                'phone' => 'phone'
            ]);

            if (!empty($errors)) {
                return Response::validationError($errors);
            }

            $this->beginTransaction();

            // Check if user exists
            $stmt = $this->pdo->prepare("SELECT id FROM User WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->rollback();
                return Response::notFound('User not found');
            }

            // Update user
            $updates = [];
            $params = [];

            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }

            if (isset($data['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (isset($data['roleId'])) {
                $updates[] = "roleId = ?";
                $params[] = $data['roleId'];
            }

            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }

            if (!empty($updates)) {
                $updates[] = "updatedAt = NOW()";
                $params[] = $id;

                $sql = "UPDATE User SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            // Update user details
            $updates = [];
            $params = [];

            if (isset($data['firstName'])) {
                $updates[] = "firstName = ?";
                $params[] = $data['firstName'];
            }

            if (isset($data['lastName'])) {
                $updates[] = "lastName = ?";
                $params[] = $data['lastName'];
            }

            if (isset($data['phone'])) {
                $updates[] = "phone = ?";
                $params[] = $data['phone'];
            }

            if (!empty($updates)) {
                $updates[] = "updatedAt = NOW()";
                $params[] = $id;

                $sql = "UPDATE UserDetails SET " . implode(", ", $updates) . " WHERE userId = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();

            return Response::success(null, 'User updated successfully');
        } catch (\Exception $e) {
            $this->rollback();
            return $this->handleError($e);
        }
    }

    public function deleteUser($id) {
        try {
            $this->beginTransaction();

            // Check if user exists
            $stmt = $this->pdo->prepare("SELECT id FROM User WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                $this->rollback();
                return Response::notFound('User not found');
            }

            // Delete user details
            $stmt = $this->pdo->prepare("DELETE FROM UserDetails WHERE userId = ?");
            $stmt->execute([$id]);

            // Delete user
            $stmt = $this->pdo->prepare("DELETE FROM User WHERE id = ?");
            $stmt->execute([$id]);

            $this->commit();

            return Response::success(null, 'User deleted successfully');
        } catch (\Exception $e) {
            $this->rollback();
            return $this->handleError($e);
        }
    }
} 