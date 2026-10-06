<?php

namespace App\Exceptions;

use RuntimeException;

class FileProcessingFailedException extends RuntimeException
{
    public function __construct(string $message, string $stage)
    {
        parent::__construct($message, crc32($stage));
    }
}
