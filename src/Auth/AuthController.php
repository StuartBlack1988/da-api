<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController {
    private $secretKey;
    private $db;

    public function __construct($db) {
        $this->secretKey = getenv('JWT_SECRET_KEY');
        $this->db = $db;
    }

    public function register($data) {
        // Validate input
        if (empty($data['email']) || empty($data['password']) || empty($data['name']) || empty($data['surname'])) {
            return ['error' => 'Email, password, name, and surname are required'];
        }

        // Check if user exists
        $stmt = $this->db->prepare("SELECT userId FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['error' => 'User already exists'];
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Create user
        $stmt = $this->db->prepare("INSERT INTO users (email, password, name, surname) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['email'], $hashedPassword, $data['name'], $data['surname']]);

        return ['message' => 'User registered successfully'];
    }

    public function login($data) {
        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return ['error' => 'Email and password are required'];
        }

        // Get user
        $stmt = $this->db->prepare("SELECT userId, password FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            return ['error' => 'Invalid credentials'];
        }

        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET lastLogin = CURRENT_TIMESTAMP WHERE userId = ?");
        $stmt->execute([$user['userId']]);

        // Generate JWT token
        $token = $this->generateToken($user['userId']);

        return [
            'message' => 'Login successful',
            'token' => $token
        ];
    }

    public function setPassword($data) {
        // Validate input
        if (empty($data['token']) || empty($data['password'])) {
            return ['error' => 'Token and password are required'];
        }

        try {
            $decoded = JWT::decode($data['token'], new Key($this->secretKey, 'HS256'));
            $userId = $decoded->sub;

            // Hash new password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Update password
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE userId = ?");
            $stmt->execute([$hashedPassword, $userId]);

            return ['message' => 'Password updated successfully'];
        } catch (\Exception $e) {
            return ['error' => 'Invalid token'];
        }
    }

    public function updateUser($data, $userId) {
        // Validate input
        if (empty($data['email'])) {
            return ['error' => 'Email is required'];
        }

        // Update user
        $stmt = $this->db->prepare("UPDATE users SET email = ?, name = ?, surname = ? WHERE userId = ?");
        $stmt->execute([$data['email'], $data['name'] ?? '', $data['surname'] ?? '', $userId]);

        return ['message' => 'User updated successfully'];
    }

    public function resetPassword($data) {
        // Validate input
        if (empty($data['email'])) {
            return ['error' => 'Email is required'];
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store reset token
        $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $stmt->execute([$resetToken, $expiry, $data['email']]);

        // TODO: Send email with reset link
        return ['message' => 'Password reset instructions sent to email'];
    }

    private function generateToken($userId) {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // 1 hour

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'sub' => $userId
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }
} 