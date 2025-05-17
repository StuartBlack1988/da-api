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

    public function runMigrations() {
        try {
            // Start a transaction for all migrations
            $this->db->beginTransaction();

            // Get already applied migrations
            $appliedMigrations = $this->getAppliedMigrations();

            // Get all migration files
            $migrationFiles = glob($this->migrationsPath . '*.php');
            sort($migrationFiles); // Ensure migrations run in order

            $newlyAppliedMigrations = [];
            $errors = [];

            foreach ($migrationFiles as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                require_once $file;

                // Extract the actual class name from the file
                $fileContent = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $fileContent, $matches)) {
                    $className = 'DietitianAssist\\Migration\\' . $matches[1];
                }

                // Extract version number from filename (e.g., "001" from "001_initial_setup.php")
                if (preg_match('/^(\d+)_/', basename($file), $matches)) {
                    $version = $matches[1];
                    
                    // Skip if migration is already applied
                    if (in_array($version, $appliedMigrations)) {
                        continue;
                    }

                    if (!class_exists($className)) {
                        $errors[] = "Migration class {$className} not found in {$file}";
                        continue;
                    }

                    try {
                        $migration = new $className();
                        $migration->up($this->db);
                        
                        // Record the migration in SchemaVersion
                        $stmt = $this->db->prepare("
                            INSERT INTO SchemaVersion (version, description) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$version, "Migration {$version} applied"]);
                        
                        $newlyAppliedMigrations[] = $className;
                    } catch (\Exception $e) {
                        $errors[] = "Error running migration {$className}: " . $e->getMessage();
                        throw $e; // Re-throw to trigger rollback
                    }
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

            $rolledBackMigrations = [];
            $errors = [];

            foreach ($appliedMigrations as $version) {
                $migrationFile = glob($this->migrationsPath . $version . '_*.php');
                if (empty($migrationFile)) {
                    $errors[] = "Migration file for version {$version} not found";
                    continue;
                }

                $file = $migrationFile[0];
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
                    $stmt->execute([$version]);
                    
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