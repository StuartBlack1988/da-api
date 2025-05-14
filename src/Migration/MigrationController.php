<?php

namespace DietitianAssist\Migration;

use DietitianAssist\Core\Database;
use DietitianAssist\Core\ApiResponse;

class MigrationController {
    private $db;
    private $migrationsPath;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->migrationsPath = __DIR__ . '/../../database/migrations/';
    }

    public function runMigrations() {
        try {
            // Get all migration files
            $migrationFiles = glob($this->migrationsPath . '*.php');
            sort($migrationFiles); // Ensure migrations run in order

            $appliedMigrations = [];
            $errors = [];

            foreach ($migrationFiles as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                require_once $file;

                if (!class_exists($className)) {
                    $errors[] = "Migration class {$className} not found in {$file}";
                    continue;
                }

                try {
                    $migration = new $className();
                    $migration->up($this->db);
                    $appliedMigrations[] = $className;
                } catch (\Exception $e) {
                    $errors[] = "Error running migration {$className}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                return ApiResponse::error(implode("\n", $errors));
            }

            return ApiResponse::success([
                'message' => 'Migrations completed successfully',
                'applied_migrations' => $appliedMigrations
            ]);

        } catch (\Exception $e) {
            return ApiResponse::error('Migration failed: ' . $e->getMessage());
        }
    }

    public function rollbackMigrations() {
        try {
            // Get all migration files in reverse order
            $migrationFiles = glob($this->migrationsPath . '*.php');
            rsort($migrationFiles); // Run migrations in reverse order

            $rolledBackMigrations = [];
            $errors = [];

            foreach ($migrationFiles as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                require_once $file;

                if (!class_exists($className)) {
                    $errors[] = "Migration class {$className} not found in {$file}";
                    continue;
                }

                try {
                    $migration = new $className();
                    $migration->down($this->db);
                    $rolledBackMigrations[] = $className;
                } catch (\Exception $e) {
                    $errors[] = "Error rolling back migration {$className}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                return ApiResponse::error(implode("\n", $errors));
            }

            return ApiResponse::success([
                'message' => 'Migrations rolled back successfully',
                'rolled_back_migrations' => $rolledBackMigrations
            ]);

        } catch (\Exception $e) {
            return ApiResponse::error('Rollback failed: ' . $e->getMessage());
        }
    }
} 