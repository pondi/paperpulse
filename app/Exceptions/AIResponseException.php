<?php

namespace App\Exceptions;

use RuntimeException;

class AIResponseException extends RuntimeException
{
    public const CODE_USAGE_BUDGET_EXCEEDED = 'usage_budget_exceeded';

    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?string $errorCode = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
