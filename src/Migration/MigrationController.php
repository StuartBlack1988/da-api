<?php

namespace DietitianAssist\Migration;

use PDO;
use DietitianAssist\Core\ApiResponse;

class MigrationController {
    private $db;
    private $migrationsPath;
    private $logs = [];

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->migrationsPath = __DIR__ . '/../../database/migrations/';
    }

    private function log($message) {
        $this->logs[] = date('Y-m-d H:i:s') . ' - ' . $message;
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
            $this->logs = []; // Reset logs
            $this->log("Starting migration process");

            // Start a transaction for all migrations
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $this->log("Transaction started");
            }

            // Get lists of migrations
            $appliedMigrations = $this->getAppliedMigrations();
            $availableMigrations = $this->getAvailableMigrations();
            $this->log("Found " . count($availableMigrations) . " available migrations");
            $this->log("Found " . count($appliedMigrations) . " applied migrations");

            // Find migrations that need to be run
            $migrationsToRun = array_diff($availableMigrations, $appliedMigrations);
            if (empty($migrationsToRun)) {
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                    $this->log("No new migrations to run, committing transaction");
                }
                return ApiResponse::success([
                    'message' => 'No new migrations to run',
                    'applied_migrations' => [],
                    'logs' => $this->logs
                ]);
            }

            $newlyAppliedMigrations = [];
            $errors = [];

            foreach ($migrationsToRun as $filename) {
                $this->log("Processing migration: " . $filename);
                $file = $this->migrationsPath . $filename;
                require_once $file;

                // Extract the actual class name from the file
                $fileContent = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $fileContent, $matches)) {
                    $className = 'DietitianAssist\\Migration\\' . $matches[1];
                }

                if (!class_exists($className)) {
                    $error = "Migration class {$className} not found in {$file}";
                    $this->log("ERROR: " . $error);
                    $errors[] = $error;
                    continue;
                }

                try {
                    $this->log("Instantiating migration class: " . $className);
                    $migration = new $className();
                    
                    // Set up logging callback
                    $migration->setLogCallback(function($message) {
                        $this->log($message);
                    });
                    
                    $this->log("Running migration up() method");
                    $migration->up($this->db);
                    
                    // Record the migration in SchemaVersion with full filename
                    $this->log("Recording migration in SchemaVersion table");
                    $stmt = $this->db->prepare("
                        INSERT INTO SchemaVersion (version, description) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$filename, "Migration {$filename} applied"]);
                    
                    $newlyAppliedMigrations[] = $className;
                    $this->log("Successfully completed migration: " . $filename);
                } catch (\Exception $e) {
                    $error = "Error running migration {$className}: " . $e->getMessage();
                    $this->log("ERROR: " . $error);
                    $this->log("Stack trace: " . $e->getTraceAsString());
                    $errors[] = $error;
                    throw $e; // Re-throw to trigger rollback
                }
            }

            if (!empty($errors)) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                    $this->log("Rolling back transaction due to errors");
                }
                return ApiResponse::error(implode("\n", $errors), 500, [
                    'logs' => $this->logs
                ]);
            }

            // Commit all migrations
            if ($this->db->inTransaction()) {
                $this->db->commit();
                $this->log("Successfully committed all migrations");
            }

            return ApiResponse::success([
                'message' => 'Migrations completed successfully',
                'applied_migrations' => $newlyAppliedMigrations,
                'logs' => $this->logs
            ]);

        } catch (\Exception $e) {
            // Rollback on any error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                $this->log("Rolling back transaction due to exception");
            }
            $this->log("FATAL ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            return ApiResponse::error('Migration failed: ' . $e->getMessage(), 500, [
                'logs' => $this->logs
            ]);
        }
    }

    public function rollbackMigrations() {
        try {
            $this->logs = []; // Reset logs
            $this->log("Starting rollback process");

            // Start a transaction for all rollbacks
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $this->log("Transaction started");
            }

            // Get applied migrations in reverse order
            $stmt = $this->db->query("SELECT version FROM SchemaVersion ORDER BY versionId DESC");
            $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->log("Found " . count($appliedMigrations) . " migrations to roll back");

            if (empty($appliedMigrations)) {
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                    $this->log("No migrations to roll back, committing transaction");
                }
                return ApiResponse::success([
                    'message' => 'No migrations to roll back',
                    'rolled_back_migrations' => [],
                    'logs' => $this->logs
                ]);
            }

            $rolledBackMigrations = [];
            $errors = [];

            foreach ($appliedMigrations as $filename) {
                $this->log("Processing rollback for: " . $filename);
                $file = $this->migrationsPath . $filename;
                if (!file_exists($file)) {
                    $error = "Migration file {$filename} not found";
                    $this->log("ERROR: " . $error);
                    $errors[] = $error;
                    continue;
                }

                require_once $file;

                // Extract the actual class name from the file
                $fileContent = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $fileContent, $matches)) {
                    $className = 'DietitianAssist\\Migration\\' . $matches[1];
                }

                if (!class_exists($className)) {
                    $error = "Migration class {$className} not found in {$file}";
                    $this->log("ERROR: " . $error);
                    $errors[] = $error;
                    continue;
                }

                try {
                    $this->log("Instantiating migration class: " . $className);
                    $migration = new $className();
                    
                    // Set up logging callback
                    $migration->setLogCallback(function($message) {
                        $this->log($message);
                    });
                    
                    $this->log("Running migration down() method");
                    $migration->down($this->db);
                    
                    // Remove the migration record from SchemaVersion
                    $this->log("Removing migration record from SchemaVersion");
                    $stmt = $this->db->prepare("DELETE FROM SchemaVersion WHERE version = ?");
                    $stmt->execute([$filename]);
                    
                    $rolledBackMigrations[] = $className;
                    $this->log("Successfully rolled back migration: " . $filename);
                } catch (\Exception $e) {
                    $error = "Error rolling back migration {$className}: " . $e->getMessage();
                    $this->log("ERROR: " . $error);
                    $this->log("Stack trace: " . $e->getTraceAsString());
                    $errors[] = $error;
                    throw $e; // Re-throw to trigger rollback
                }
            }

            if (!empty($errors)) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                    $this->log("Rolling back transaction due to errors");
                }
                return ApiResponse::error(implode("\n", $errors), 500, [
                    'logs' => $this->logs
                ]);
            }

            // Commit all rollbacks
            if ($this->db->inTransaction()) {
                $this->db->commit();
                $this->log("Successfully committed all rollbacks");
            }

            return ApiResponse::success([
                'message' => 'Migrations rolled back successfully',
                'rolled_back_migrations' => $rolledBackMigrations,
                'logs' => $this->logs
            ]);

        } catch (\Exception $e) {
            // Rollback on any error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                $this->log("Rolling back transaction due to exception");
            }
            $this->log("FATAL ERROR: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            return ApiResponse::error('Rollback failed: ' . $e->getMessage(), 500, [
                'logs' => $this->logs
            ]);
        }
    }
} 