<?php

namespace App\Core\Database;

use RuntimeException;
use Throwable;

class MigrationException extends RuntimeException
{
    public function __construct(string $message, private string $migrationFile, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function migrationFile(): string
    {
        return $this->migrationFile;
    }
}
