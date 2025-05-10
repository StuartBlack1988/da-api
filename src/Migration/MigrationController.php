<?php

namespace DietitianAssist\Migration;

use PDO;

class MigrationController {
    private $db;
    private $migrationsTable = 'migrations';
    private $migrationsPath;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->migrationsPath = __DIR__ . '/../../database/migrations';
    }

    public function handleMigration($request) {
        try {
            $targetMigration = $request['migration'] ?? null;
            
            if ($targetMigration) {
                return $this->migrateToVersion($targetMigration);
            } else {
                return $this->runAllMigrations();
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function ensureMigrationsTable() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `batch` INT NOT NULL,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function getMigrations() {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        return $files;
    }

    private function getExecutedMigrations() {
        $stmt = $this->db->query("SELECT migration FROM {$this->migrationsTable}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getNextBatchNumber() {
        $stmt = $this->db->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
        return ($stmt->fetchColumn() ?: 0) + 1;
    }

    private function runAllMigrations() {
        $this->ensureMigrationsTable();
        $migrations = $this->getMigrations();
        $executed = $this->getExecutedMigrations();
        $batch = $this->getNextBatchNumber();
        $count = 0;
        $executedMigrations = [];

        foreach ($migrations as $file) {
            $migration = basename($file, '.php');
            if (!in_array($migration, $executed)) {
                require_once $file;
                $class = str_replace('.php', '', $migration);
                
                try {
                    $this->db->beginTransaction();
                    
                    $instance = new $class();
                    $instance->up($this->db);
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO {$this->migrationsTable} (migration, batch) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$migration, $batch]);
                    
                    $this->db->commit();
                    $executedMigrations[] = $migration;
                    $count++;
                } catch (\Exception $e) {
                    $this->db->rollBack();
                    throw new \Exception("Error migrating {$migration}: " . $e->getMessage());
                }
            }
        }

        return [
            'status' => 'success',
            'message' => $count === 0 ? 'No new migrations to run.' : "Completed {$count} migration(s).",
            'migrations' => $executedMigrations
        ];
    }

    private function migrateToVersion($targetMigration) {
        $this->ensureMigrationsTable();
        $migrations = $this->getMigrations();
        $executed = $this->getExecutedMigrations();
        $targetIndex = array_search($targetMigration, array_map('basename', $migrations, array_fill(0, count($migrations), '.php')));
        
        if ($targetIndex === false) {
            throw new \Exception("Migration {$targetMigration} not found.");
        }

        $currentIndex = -1;
        foreach ($executed as $migration) {
            $index = array_search($migration, array_map('basename', $migrations, array_fill(0, count($migrations), '.php')));
            if ($index !== false) {
                $currentIndex = $index;
            }
        }

        if ($targetIndex < $currentIndex) {
            return $this->rollbackToVersion($targetMigration);
        } else {
            return $this->migrateUpToVersion($targetMigration);
        }
    }

    private function migrateUpToVersion($targetMigration) {
        $migrations = $this->getMigrations();
        $executed = $this->getExecutedMigrations();
        $batch = $this->getNextBatchNumber();
        $count = 0;
        $executedMigrations = [];

        foreach ($migrations as $file) {
            $migration = basename($file, '.php');
            if (!in_array($migration, $executed)) {
                require_once $file;
                $class = str_replace('.php', '', $migration);
                
                try {
                    $this->db->beginTransaction();
                    
                    $instance = new $class();
                    $instance->up($this->db);
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO {$this->migrationsTable} (migration, batch) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$migration, $batch]);
                    
                    $this->db->commit();
                    $executedMigrations[] = $migration;
                    $count++;

                    if ($migration === $targetMigration) {
                        break;
                    }
                } catch (\Exception $e) {
                    $this->db->rollBack();
                    throw new \Exception("Error migrating {$migration}: " . $e->getMessage());
                }
            }
        }

        return [
            'status' => 'success',
            'message' => $count === 0 ? 'No new migrations to run.' : "Completed {$count} migration(s).",
            'migrations' => $executedMigrations
        ];
    }

    private function rollbackToVersion($targetMigration) {
        $migrations = $this->getMigrations();
        $executed = $this->getExecutedMigrations();
        $count = 0;
        $rolledBackMigrations = [];

        // Get migrations in reverse order
        $migrationsToRollback = array_reverse($executed);
        
        foreach ($migrationsToRollback as $migration) {
            $file = $this->migrationsPath . '/' . $migration . '.php';
            if (file_exists($file)) {
                require_once $file;
                $class = str_replace('.php', '', $migration);
                
                try {
                    $this->db->beginTransaction();
                    
                    $instance = new $class();
                    $instance->down($this->db);
                    
                    $stmt = $this->db->prepare("
                        DELETE FROM {$this->migrationsTable} 
                        WHERE migration = ?
                    ");
                    $stmt->execute([$migration]);
                    
                    $this->db->commit();
                    $rolledBackMigrations[] = $migration;
                    $count++;

                    if ($migration === $targetMigration) {
                        break;
                    }
                } catch (\Exception $e) {
                    $this->db->rollBack();
                    throw new \Exception("Error rolling back {$migration}: " . $e->getMessage());
                }
            }
        }

        return [
            'status' => 'success',
            'message' => $count === 0 ? 'No migrations to roll back.' : "Rolled back {$count} migration(s).",
            'migrations' => $rolledBackMigrations
        ];
    }
} 