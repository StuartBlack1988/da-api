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
        // Get only the numeric version numbers, not the full filenames
        $stmt = $this->db->query("
            SELECT DISTINCT SUBSTRING_INDEX(version, '_', 1) as version 
            FROM SchemaVersion 
            ORDER BY CAST(SUBSTRING_INDEX(version, '_', 1) AS UNSIGNED)
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getAvailableMigrations() {
        $migrationFiles = glob($this->migrationsPath . '*.php');
        sort($migrationFiles); // Ensure migrations are in order
        return array_map(function($file) {
            // Extract just the version number from the filename
            $filename = basename($file);
            return substr($filename, 0, strpos($filename, '_'));
        }, $migrationFiles);
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

            // Clean up any duplicate entries in SchemaVersion
            $this->log("Cleaning up duplicate entries in SchemaVersion");
            $this->db->exec("
                DELETE t1 FROM SchemaVersion t1
                INNER JOIN SchemaVersion t2
                WHERE t1.versionId > t2.versionId
                AND SUBSTRING_INDEX(t1.version, '_', 1) = SUBSTRING_INDEX(t2.version, '_', 1)
            ");

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

            foreach ($migrationsToRun as $version) {
                // Find the corresponding PHP file
                $migrationFiles = glob($this->migrationsPath . $version . '_*.php');
                if (empty($migrationFiles)) {
                    $error = "No migration file found for version {$version}";
                    $this->log("ERROR: " . $error);
                    $errors[] = $error;
                    continue;
                }
                $filename = basename($migrationFiles[0]);
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
                    
                    // Record the migration in SchemaVersion with just the version number
                    $this->log("Recording migration in SchemaVersion table");
                    $stmt = $this->db->prepare("
                        INSERT INTO SchemaVersion (version, description) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$version, "Migration {$filename} applied"]);
                    
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
            $stmt = $this->db->query("
                SELECT DISTINCT SUBSTRING_INDEX(version, '_', 1) as version 
                FROM SchemaVersion 
                ORDER BY CAST(SUBSTRING_INDEX(version, '_', 1) AS UNSIGNED) DESC
            ");
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

            foreach ($appliedMigrations as $version) {
                // Find the corresponding PHP file
                $migrationFiles = glob($this->migrationsPath . $version . '_*.php');
                if (empty($migrationFiles)) {
                    $error = "No migration file found for version {$version}";
                    $this->log("ERROR: " . $error);
                    $errors[] = $error;
                    continue;
                }
                $filename = basename($migrationFiles[0]);
                $this->log("Processing rollback for: " . $filename);
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
                    
                    $this->log("Running migration down() method");
                    $migration->down($this->db);
                    
                    // Remove all migration records for this version from SchemaVersion
                    $this->log("Removing migration records from SchemaVersion");
                    $stmt = $this->db->prepare("
                        DELETE FROM SchemaVersion 
                        WHERE SUBSTRING_INDEX(version, '_', 1) = ?
                    ");
                    $stmt->execute([$version]);
                    
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