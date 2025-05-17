<?php

namespace DietitianAssist\Migration;

use PDO;
use DietitianAssist\Core\ApiResponse;

class MigrationController {
    private $db;
    private $migrationsPath;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->migrationsPath = __DIR__ . '/../../database/migrations/';
    }

    private function getAppliedMigrations() {
        $stmt = $this->db->query("SELECT version FROM SchemaVersion ORDER BY versionId");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getAvailableMigrations() {
        $migrationFiles = glob($this->migrationsPath . '*.php');
        sort($migrationFiles); // Ensure migrations are in order
        return array_map('basename', $migrationFiles);
    }

    public function runMigrations() {
        try {
            // Start a transaction for all migrations
            $this->db->beginTransaction();

            // Get lists of migrations
            $appliedMigrations = $this->getAppliedMigrations();
            $availableMigrations = $this->getAvailableMigrations();

            // Find migrations that need to be run
            $migrationsToRun = array_diff($availableMigrations, $appliedMigrations);
            if (empty($migrationsToRun)) {
                return ApiResponse::success([
                    'message' => 'No new migrations to run',
                    'applied_migrations' => []
                ]);
            }

            $newlyAppliedMigrations = [];
            $errors = [];

            foreach ($migrationsToRun as $filename) {
                $file = $this->migrationsPath . $filename;
                require_once $file;

                // Extract the actual class name from the file
                $fileContent = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $fileContent, $matches)) {
                    $className = 'DietitianAssist\\Migration\\' . $matches[1];
                }

                if (!class_exists($className)) {
                    $errors[] = "Migration class {$className} not found in {$file}";
                    continue;
                }

                try {
                    $migration = new $className();
                    $migration->up($this->db);
                    
                    // Record the migration in SchemaVersion with full filename
                    $stmt = $this->db->prepare("
                        INSERT INTO SchemaVersion (version, description) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$filename, "Migration {$filename} applied"]);
                    
                    $newlyAppliedMigrations[] = $className;
                } catch (\Exception $e) {
                    $errors[] = "Error running migration {$className}: " . $e->getMessage();
                    throw $e; // Re-throw to trigger rollback
                }
            }

            if (!empty($errors)) {
                $this->db->rollBack();
                return ApiResponse::error(implode("\n", $errors));
            }

            // Commit all migrations
            $this->db->commit();

            return ApiResponse::success([
                'message' => 'Migrations completed successfully',
                'applied_migrations' => $newlyAppliedMigrations
            ]);

        } catch (\Exception $e) {
            // Rollback on any error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ApiResponse::error('Migration failed: ' . $e->getMessage());
        }
    }

    public function rollbackMigrations() {
        try {
            // Start a transaction for all rollbacks
            $this->db->beginTransaction();

            // Get applied migrations in reverse order
            $stmt = $this->db->query("SELECT version FROM SchemaVersion ORDER BY versionId DESC");
            $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($appliedMigrations)) {
                return ApiResponse::success([
                    'message' => 'No migrations to roll back',
                    'rolled_back_migrations' => []
                ]);
            }

            $rolledBackMigrations = [];
            $errors = [];

            foreach ($appliedMigrations as $filename) {
                $file = $this->migrationsPath . $filename;
                if (!file_exists($file)) {
                    $errors[] = "Migration file {$filename} not found";
                    continue;
                }

                require_once $file;

                // Extract the actual class name from the file
                $fileContent = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $fileContent, $matches)) {
                    $className = 'DietitianAssist\\Migration\\' . $matches[1];
                }

                if (!class_exists($className)) {
                    $errors[] = "Migration class {$className} not found in {$file}";
                    continue;
                }

                try {
                    $migration = new $className();
                    $migration->down($this->db);
                    
                    // Remove the migration record from SchemaVersion
                    $stmt = $this->db->prepare("DELETE FROM SchemaVersion WHERE version = ?");
                    $stmt->execute([$filename]);
                    
                    $rolledBackMigrations[] = $className;
                } catch (\Exception $e) {
                    $errors[] = "Error rolling back migration {$className}: " . $e->getMessage();
                    throw $e; // Re-throw to trigger rollback
                }
            }

            if (!empty($errors)) {
                $this->db->rollBack();
                return ApiResponse::error(implode("\n", $errors));
            }

            // Commit all rollbacks
            $this->db->commit();

            return ApiResponse::success([
                'message' => 'Migrations rolled back successfully',
                'rolled_back_migrations' => $rolledBackMigrations
            ]);

        } catch (\Exception $e) {
            // Rollback on any error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ApiResponse::error('Rollback failed: ' . $e->getMessage());
        }
    }
} 