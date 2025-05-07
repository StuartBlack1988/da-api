<?php

namespace DietitianAssist\Role;

use PDO;
use PDOException;

class RoleController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getRoleByName($name) {
        $stmt = $this->db->prepare("SELECT roleId, name, description FROM `Role` WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public function getRoleById($roleId) {
        $stmt = $this->db->prepare("SELECT roleId, name, description FROM `Role` WHERE roleId = ?");
        $stmt->execute([$roleId]);
        return $stmt->fetch();
    }

    public function getAllRoles() {
        $stmt = $this->db->prepare("SELECT roleId, name, description FROM `Role` ORDER BY roleId");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updateUserRole($userId, $roleId) {
        $stmt = $this->db->prepare("UPDATE `User` SET roleId = ? WHERE userId = ?");
        return $stmt->execute([$roleId, $userId]);
    }

    public function getPendingRole($isClient) {
        return $this->getRoleByName($isClient ? 'pending-client' : 'pending-patient');
    }

    public function getActiveRole($isClient) {
        return $this->getRoleByName($isClient ? 'client' : 'patient');
    }
} 