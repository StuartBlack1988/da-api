<?php

namespace DietitianAssist\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use DietitianAssist\Token\TokenController;
use DietitianAssist\Role\RoleController;
use PDOException;

class AuthController {
    private $db;
    private $jwtSecretKey;
    private $tokenController;
    private $roleController;

    public function __construct($db) {
        $this->db = $db;
        $this->jwtSecretKey = $_ENV['JWT_SECRET_KEY'] ?? null;
        $this->tokenController = new TokenController($db);
        $this->roleController = new RoleController($db);
        
        if (!$this->jwtSecretKey) {
            throw new \Exception('JWT_SECRET_KEY is not set in environment variables');
        }
    }

    public function register($data) {
        // Validate input
        if (empty($data['email']) || empty($data['name']) || empty($data['surname']) || !isset($data['isClient'])) {
            return ['error' => 'Email, name, surname, and isClient are required'];
        }

        // Check if user exists
        $stmt = $this->db->prepare("SELECT userId FROM `User` WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['error' => 'User already exists'];
        }

        // Get pending role based on isClient
        $role = $this->roleController->getPendingRole($data['isClient']);
        if (!$role) {
            return ['error' => 'Invalid role configuration'];
        }

        // Create user with null password and pending role
        $stmt = $this->db->prepare("INSERT INTO `User` (email, name, surname, roleId) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['email'],
            $data['name'],
            $data['surname'],
            $role['roleId']
        ]);

        $userId = $this->db->lastInsertId();
        
        // Create set-password token
        $token = $this->tokenController->createToken($userId, 'set-password', 24);

        // Send email with set-password link
        $this->sendSetPasswordEmail($data['email'], $token);

        return [
            'message' => 'Registration successful. Please check your email to set your password.'
        ];
    }

    private function sendSetPasswordEmail($email, $token) {
        $setPasswordUrl = str_replace('api.', 'www.', $_ENV['APP_URL']) . "/set-password/" . $token;
        
        $to = $email;
        $subject = "Set Your Password - Dietitian Assist";
        $message = "
        <html>
        <head>
            <title>Set Your Password</title>
        </head>
        <body>
            <h2>Welcome to Dietitian Assist!</h2>
            <p>Thank you for registering. To complete your registration, please set your password by clicking the link below:</p>
            <p><a href='{$setPasswordUrl}'>Set Your Password</a></p>
            <p>This link will expire in 24 hours.</p>
            <p>If you did not register for Dietitian Assist, please ignore this email.</p>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: ' . $_ENV['MAIL_FROM_NAME'] . ' <' . $_ENV['MAIL_FROM_ADDRESS'] . '>' . "\r\n";

        mail($to, $subject, $message, $headers);
    }

    public function login($data) {
        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return ['error' => 'Email and password are required'];
        }

        // Get user with role
        $stmt = $this->db->prepare("
            SELECT u.userId, u.password, u.isActive, r.name as role 
            FROM `User` u 
            JOIN `Role` r ON u.roleId = r.roleId 
            WHERE u.email = ?
        ");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !$user['password'] || !password_verify($data['password'], $user['password'])) {
            return ['error' => 'Invalid credentials'];
        }

        // Check if user is in pending state
        if (strpos($user['role'], 'pending-') === 0) {
            http_response_code(200);
            return ['error' => 'Please set your password first'];
        }

        // Check if user is active
        if (!$user['isActive']) {
            return ['error' => 'Account is deactivated. Please contact support.'];
        }

        // Update last login
        $stmt = $this->db->prepare("UPDATE `User` SET lastLogin = CURRENT_TIMESTAMP WHERE userId = ?");
        $stmt->execute([$user['userId']]);

        // Create login token
        $token = $this->tokenController->createToken($user['userId'], 'login', 24);

        return [
            'message' => 'Login successful',
            'token' => $token,
            'role' => $user['role']
        ];
    }

    public function setPassword($data) {
        // Validate input
        if (!isset($data['token']) || !isset($data['password'])) {
            return ['error' => 'Token and password are required'];
        }

        // Validate password
        if (strlen($data['password']) < 8) {
            return ['error' => 'Password must be at least 8 characters long'];
        }

        // Check if password meets complexity requirements
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d])[A-Za-z\d\W]{8,}$/', $data['password'])) {
            return ['error' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'];
        }

        try {
            // Get token data
            $stmt = $this->db->prepare("SELECT userId, tokenType, expiryDateTime, isUsed FROM `Token` WHERE token = ?");
            $stmt->execute([$data['token']]);
            $tokenData = $stmt->fetch();

            if (!$tokenData) {
                return ['error' => 'Invalid token'];
            }

            // Check if token is expired
            if (strtotime($tokenData['expiryDateTime']) < time()) {
                return ['error' => 'Token has expired'];
            }

            // Check if token is already used
            if ($tokenData['isUsed']) {
                return ['error' => 'Token has already been used'];
            }

            // Check if token type is set-password
            if ($tokenData['tokenType'] !== 'set-password') {
                return ['error' => 'Invalid token type'];
            }

            // Hash the password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Get user's current role
            $stmt = $this->db->prepare("
                SELECT u.roleId, r.name as roleName 
                FROM `User` u 
                JOIN `Role` r ON u.roleId = r.roleId 
                WHERE u.userId = ?
            ");
            $stmt->execute([$tokenData['userId']]);
            $user = $stmt->fetch();

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Update the password
                $stmt = $this->db->prepare("UPDATE `User` SET password = ? WHERE userId = ?");
                $stmt->execute([$hashedPassword, $tokenData['userId']]);

                // Update role if user is in pending state
                if (strpos($user['roleName'], 'pending-') === 0) {
                    $newRole = str_replace('pending-', '', $user['roleName']);
                    $stmt = $this->db->prepare("
                        UPDATE `User` u 
                        JOIN `Role` r ON r.name = ? 
                        SET u.roleId = r.roleId 
                        WHERE u.userId = ?
                    ");
                    $stmt->execute([$newRole, $tokenData['userId']]);
                }

                // Mark token as used
                $stmt = $this->db->prepare("UPDATE `Token` SET isUsed = TRUE WHERE token = ?");
                $stmt->execute([$data['token']]);

                $this->db->commit();
                return ['message' => 'Password set successfully'];
            } catch (PDOException $e) {
                $this->db->rollBack();
                error_log("Error setting password: " . $e->getMessage());
                return ['error' => 'Failed to set password'];
            }
        } catch (PDOException $e) {
            error_log("Error setting password: " . $e->getMessage());
            return ['error' => 'Failed to set password'];
        }
    }

    public function resetPassword($data) {
        // Validate input
        if (empty($data['email'])) {
            return ['error' => 'Email is required'];
        }

        // Get user
        $stmt = $this->db->prepare("SELECT userId FROM `User` WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['error' => 'User not found'];
        }

        // Create set-password token
        $token = $this->tokenController->createToken($user['userId'], 'set-password', 1);

        // Send email with reset link
        $this->sendSetPasswordEmail($data['email'], $token);

        return [
            'message' => 'Password reset instructions sent to email'
        ];
    }

    public function validateToken($token, $tokenType) {
        return $this->tokenController->validateToken($token, $tokenType);
    }

    public function markTokenAsUsed($token) {
        return $this->tokenController->markTokenAsUsed($token);
    }

    public function validateTokenEndpoint($data) {
        // Validate input
        if (empty($data['token']) || empty($data['type'])) {
            return ['error' => 'Token and type are required'];
        }

        try {
            // Get token data
            $stmt = $this->db->prepare("
                SELECT userId, tokenType, expiryDateTime, isUsed 
                FROM `Token` 
                WHERE token = ? AND tokenType = ?
            ");
            $stmt->execute([$data['token'], $data['type']]);
            $tokenData = $stmt->fetch();

            if (!$tokenData) {
                http_response_code(200);
                return ['error' => 'Your token is invalid or has expired, please request a new password link'];
            }

            // Check if token is expired
            if (strtotime($tokenData['expiryDateTime']) < time()) {
                http_response_code(200);
                return ['error' => 'Your token is invalid or has expired, please request a new password link'];
            }

            // Check if token is already used
            if ($tokenData['isUsed']) {
                http_response_code(200);
                return ['error' => 'Your token is invalid or has expired, please request a new password link'];
            }

            return [
                'message' => 'Token is valid',
                'userId' => $tokenData['userId']
            ];
        } catch (PDOException $e) {
            error_log("Error validating token: " . $e->getMessage());
            http_response_code(200);
            return ['error' => 'Your token is invalid or has expired, please request a new password link'];
        }
    }
} 