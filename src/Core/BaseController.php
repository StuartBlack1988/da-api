<?php

namespace DietitianAssist\Core;

class BaseController {
    protected $pdo;
    protected $validator;
    protected $response;
    protected $logger;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
        $this->validator = new Validator();
        $this->response = new Response();
        $this->logger = new Logger();
    }

    protected function validateRequest($data, $rules) {
        $errors = [];
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field]) && strpos($rule, 'required') !== false) {
                $errors[] = "$field is required";
                continue;
            }

            $value = $data[$field] ?? null;
            if ($value === null) continue;

            if (strpos($rule, 'email') !== false && !$this->validator->validateEmail($value)) {
                $errors[] = "Invalid email format for $field";
            }
            if (strpos($rule, 'password') !== false && !$this->validator->validatePassword($value)) {
                $errors[] = "Password must be at least 8 characters and contain uppercase, lowercase, number and special character";
            }
            if (strpos($rule, 'phone') !== false && !$this->validator->validatePhone($value)) {
                $errors[] = "Invalid phone format for $field";
            }
            if (strpos($rule, 'date') !== false && !$this->validator->validateDate($value)) {
                $errors[] = "Invalid date format for $field";
            }
            if (strpos($rule, 'boolean') !== false && !$this->validator->validateBoolean($value)) {
                $errors[] = "Invalid boolean value for $field";
            }
            if (strpos($rule, 'integer') !== false && !$this->validator->validateInteger($value)) {
                $errors[] = "Invalid integer value for $field";
            }
            if (strpos($rule, 'float') !== false && !$this->validator->validateFloat($value)) {
                $errors[] = "Invalid float value for $field";
            }
        }

        return $errors;
    }

    protected function handleError(\Exception $e) {
        $this->logger->error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return $this->response->error('An error occurred', 500);
    }

    protected function beginTransaction() {
        $this->pdo->beginTransaction();
    }

    protected function commit() {
        $this->pdo->commit();
    }

    protected function rollback() {
        $this->pdo->rollBack();
    }
} 