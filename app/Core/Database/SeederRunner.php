<?php

namespace App\Core\Database;

use App\Core\ORM\ConnectionManager;
use PDO;
use Throwable;

class SeederRunner
{
    public function __construct(private ConnectionManager $connections, private string $path)
    {
    }

    public function seed(): void
    {
        $pdo = $this->connections->connection();
        foreach ($this->seeders() as $file) {
            $seeder = require $file;
            if (!$seeder instanceof Seeder) {
                throw new \RuntimeException(sprintf('Seeder %s must return an instance of %s.', $file, Seeder::class));
            }

            $startedTransaction = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            try {
                $seeder->run($pdo);
                if ($startedTransaction && $pdo->inTransaction()) {
                    $pdo->commit();
                }
            } catch (Throwable $exception) {
                if ($startedTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw new SeederException(
                    sprintf('Failed to run seeder %s: %s', basename($file), $exception->getMessage()),
                    $file,
                    $exception
                );
            }
        }
    }

    private function seeders(): array
    {
        $files = glob(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        sort($files);
        return $files;
    }
}
