<?php

namespace DietitianAssist\Core;

class Validation {
    /**
     * Validates that all required fields are present in the data array
     * 
     * @param array $data The data to validate
     * @param array $requiredFields Array of required field names
     * @return bool True if all required fields are present and not empty, false otherwise
     */
    public function validateRequiredFields(array $data, array $requiredFields): bool {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validates an email address format
     * 
     * @param string $email The email to validate
     * @return bool True if email is valid, false otherwise
     */
    public function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates a phone number format
     * 
     * @param string $phone The phone number to validate
     * @return bool True if phone number is valid, false otherwise
     */
    public function validatePhone(string $phone): bool {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Check if the resulting string is between 10 and 15 digits
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
} 