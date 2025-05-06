<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use App\Token\TokenController;

class AuthController {
    private $db;
    private $jwtSecretKey;
    private $tokenController;

    public function __construct($db) {
        $this->db = $db;
        $this->jwtSecretKey = $_ENV['JWT_SECRET_KEY'] ?? null;
        $this->tokenController = new TokenController($db);
        
        if (!$this->jwtSecretKey) {
            throw new \Exception('JWT_SECRET_KEY is not set in environment variables');
        }
    }

    public function register($data) {
        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return ['error' => 'Email and password are required'];
        }

        // Check if user exists
        $stmt = $this->db->prepare("SELECT userId FROM `User` WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['error' => 'User already exists'];
        }

        // Create user
        $stmt = $this->db->prepare("INSERT INTO `User` (email, password, name, surname) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['name'] ?? '',
            $data['surname'] ?? ''
        ]);

        $userId = $this->db->lastInsertId();
        
        // Create registration token
        $token = $this->tokenController->createToken($userId, 'register', 24);

        return [
            'message' => 'User registered successfully',
            'token' => $token
        ];
    }

    public function login($data) {
        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return ['error' => 'Email and password are required'];
        }

        // Get user
        $stmt = $this->db->prepare("SELECT userId, password FROM `User` WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            return ['error' => 'Invalid credentials'];
        }

        // Update last login
        $stmt = $this->db->prepare("UPDATE `User` SET lastLogin = CURRENT_TIMESTAMP WHERE userId = ?");
        $stmt->execute([$user['userId']]);

        // Create login token
        $token = $this->tokenController->createToken($user['userId'], 'login', 24);

        return [
            'message' => 'Login successful',
            'token' => $token
        ];
    }

    public function setPassword($data, $userId) {
        // Validate input
        if (empty($data['password'])) {
            return ['error' => 'Password is required'];
        }

        // Create set-password token
        $token = $this->tokenController->createToken($userId, 'set-password', 1);

        // Update password
        $stmt = $this->db->prepare("UPDATE `User` SET password = ? WHERE userId = ?");
        $stmt->execute([password_hash($data['password'], PASSWORD_DEFAULT), $userId]);

        return [
            'message' => 'Password updated successfully',
            'token' => $token
        ];
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

        // TODO: Send email with reset link containing token
        return [
            'message' => 'Password reset instructions sent to email',
            'token' => $token
        ];
    }

    public function validateToken($token, $tokenType) {
        return $this->tokenController->validateToken($token, $tokenType);
    }

    public function markTokenAsUsed($token) {
        return $this->tokenController->markTokenAsUsed($token);
    }
} 