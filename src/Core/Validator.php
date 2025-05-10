<?php

namespace DietitianAssist\Core;

class Validator {
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validatePassword($password) {
        return strlen($password) >= 8 
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    public static function validatePhone($phone) {
        return preg_match('/^\+?[0-9]{10,15}$/', $phone);
    }

    public static function validateRequired($value) {
        return !empty($value);
    }

    public static function validateLength($value, $min, $max = null) {
        $length = strlen($value);
        if ($max === null) {
            return $length >= $min;
        }
        return $length >= $min && $length <= $max;
    }

    public static function validateDate($date) {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public static function validateBoolean($value) {
        return is_bool($value) || in_array($value, ['0', '1', 'true', 'false', true, false], true);
    }

    public static function validateInteger($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function validateFloat($value) {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
} 