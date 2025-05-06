<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use App\Token\TokenController;
use App\Role\RoleController;

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
        $setPasswordUrl = $_ENV['APP_URL'] . "/set-password/" . $token;
        
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
            SELECT u.userId, u.password, r.name as role 
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
            return ['error' => 'Please set your password first'];
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

    public function setPassword($data, $userId) {
        // Validate input
        if (empty($data['password'])) {
            return ['error' => 'Password is required'];
        }

        // Get current user role
        $stmt = $this->db->prepare("
            SELECT r.name as role 
            FROM `User` u 
            JOIN `Role` r ON u.roleId = r.roleId 
            WHERE u.userId = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['error' => 'User not found'];
        }

        // Update password
        $stmt = $this->db->prepare("UPDATE `User` SET password = ? WHERE userId = ?");
        $stmt->execute([password_hash($data['password'], PASSWORD_DEFAULT), $userId]);

        // If user was in pending state, update to active role
        if (strpos($user['role'], 'pending-') === 0) {
            $isClient = $user['role'] === 'pending-client';
            $activeRole = $this->roleController->getActiveRole($isClient);
            if ($activeRole) {
                $this->roleController->updateUserRole($userId, $activeRole['roleId']);
            }
        }

        return ['message' => 'Password updated successfully'];
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
} 