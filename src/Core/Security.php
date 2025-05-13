<?php

namespace DietitianAssist\Core;

class Security {
    /**
     * Generates a secure random token
     * 
     * @param int $length The length of the token
     * @return string The generated token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hashes a password using PHP's password_hash
     * 
     * @param string $password The password to hash
     * @return string The hashed password
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verifies a password against a hash
     * 
     * @param string $password The password to verify
     * @param string $hash The hash to verify against
     * @return bool True if password matches hash, false otherwise
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
} 