<?php

namespace App\Core\Database;

use App\Core\ORM\ConnectionManager;
use PDO;
use Throwable;

class Migrator
{
    public function __construct(private ConnectionManager $connections, private string $path)
    {
    }

    public function migrate(): void
    {
        $pdo = $this->connections->connection();
        $this->ensureMigrationsTable($pdo);
        $files = $this->migrationFiles();
        $batch = $this->currentBatch($pdo) + 1;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($this->hasRun($pdo, $name)) {
                continue;
            }

            $migration = $this->resolve($file);
            $pdo->beginTransaction();
            try {
                $migration->up($pdo);
                $this->logMigration($pdo, $name, $batch);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw new MigrationException(
                    sprintf('Failed to run migration %s: %s', $name, $e->getMessage()),
                    $file,
                    $e
                );
            }
        }
    }

    public function rollback(): void
    {
        $pdo = $this->connections->connection();
        $this->ensureMigrationsTable($pdo);
        $batch = $this->currentBatch($pdo);
        if ($batch === 0) {
            return;
        }

        $statement = $pdo->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC');
        $statement->execute([$batch]);
        $migrations = $statement->fetchAll(PDO::FETCH_COLUMN);

        foreach ($migrations as $migrationName) {
            $file = $this->path . DIRECTORY_SEPARATOR . $migrationName . '.php';
            if (!file_exists($file)) {
                continue;
            }

            $migration = $this->resolve($file);
            $pdo->beginTransaction();
            try {
                $migration->down($pdo);
                $pdo->prepare('DELETE FROM migrations WHERE migration = ?')->execute([$migrationName]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw new MigrationException(
                    sprintf('Failed to rollback migration %s: %s', $migrationName, $e->getMessage()),
                    $file,
                    $e
                );
            }
        }
    }

    private function resolve(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new \RuntimeException(sprintf('Migration %s must return an instance of %s.', $file, Migration::class));
        }

        return $migration;
    }

    private function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function migrationFiles(): array
    {
        $files = glob(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        sort($files);
        return $files;
    }

    private function hasRun(PDO $pdo, string $migration): bool
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
        $statement->execute([$migration]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function logMigration(PDO $pdo, string $migration, int $batch): void
    {
        $statement = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $statement->execute([$migration, $batch]);
    }

    private function currentBatch(PDO $pdo): int
    {
        $statement = $pdo->query('SELECT MAX(batch) FROM migrations');
        $value = $statement->fetchColumn();
        return (int) $value;
    }
}
