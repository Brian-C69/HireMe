<?php

namespace App\Core\Database;

use RuntimeException;
use Throwable;

class SeederException extends RuntimeException
{
    public function __construct(string $message, private string $seederFile, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function seederFile(): string
    {
        return $this->seederFile;
    }
}
