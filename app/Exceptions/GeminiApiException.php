<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class GeminiApiException extends Exception
{
    public const CODE_FILE_NOT_FOUND = 'file_not_found';

    public const CODE_FILE_TOO_LARGE = 'file_too_large';

    public const CODE_UNSUPPORTED_MIME = 'unsupported_mime';

    public const CODE_RATE_LIMIT = 'rate_limited';

    public const CODE_TIMEOUT = 'timeout';

    public const CODE_API_ERROR = 'api_error';

    public const CODE_SCHEMA_INVALID = 'schema_invalid';

    public const CODE_RESPONSE_INVALID = 'response_invalid';

    protected ?string $errorCode;

    protected bool $retryable;

    protected array $context;

    public function __construct(
        string $message,
        ?string $errorCode = null,
        bool $retryable = false,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
        $this->context = $context;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
