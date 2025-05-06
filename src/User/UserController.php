<?php

namespace App\User;

class UserController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function updateUser($data, $userId) {
        // Validate input
        if (empty($data['email'])) {
            return ['error' => 'Email is required'];
        }

        // Update user
        $stmt = $this->db->prepare("UPDATE `User` SET email = ?, name = ?, surname = ? WHERE userId = ?");
        $stmt->execute([$data['email'], $data['name'] ?? '', $data['surname'] ?? '', $userId]);

        return ['message' => 'User updated successfully'];
    }

    public function getUser($userId) {
        $stmt = $this->db->prepare("SELECT userId, email, name, surname, createdDate, lastLogin FROM `User` WHERE userId = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['error' => 'User not found'];
        }

        return $user;
    }
} 