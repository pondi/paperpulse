<?php

namespace App\Services\Files;

use App\Exceptions\AIResponseException;
use App\Exceptions\FileProcessingFailedException;
use App\Exceptions\GeminiApiException;
use App\Models\File;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Laravel\Nightwatch\Facades\Nightwatch;
use Throwable;

class FileProcessingFailureReporter
{
    public function __construct(private ExceptionHandler $exceptions) {}

    /**
     * Report through the handler directly so Nightwatch treats even recovered
     * failures as issue-generating exceptions, preserving the original trace.
     *
     * @param  array<string, mixed>  $details
     */
    public function report(Throwable|string $failure, string $stage, ?File $file = null, array $details = [], bool $soft = true): void
    {
        $exception = is_string($failure) ? new FileProcessingFailedException($failure, $stage) : $failure;
        $providerContext = match (true) {
            $exception instanceof AIResponseException => $exception->context,
            $exception instanceof GeminiApiException => $exception->getContext(),
            default => [],
        };
        $failureContext = Arr::only($providerContext + $details, [
            'field', 'errors', 'dates', 'status', 'provider_attempts', 'reason', 'review', 'coverage',
            'run_calls', 'daily_calls', 'run_reserved_tokens', 'daily_reserved_tokens', 'requested_tokens',
        ]) + [
            'stage' => $stage,
            'soft' => $soft,
            'exception_class' => $exception::class,
            'code' => match (true) {
                $exception instanceof AIResponseException => $exception->errorCode,
                $exception instanceof GeminiApiException => $exception->getErrorCode(),
                default => $exception->getCode(),
            },
            'retryable' => match (true) {
                $exception instanceof AIResponseException => $exception->retryable,
                $exception instanceof GeminiApiException => $exception->isRetryable(),
                default => null,
            },
        ];
        $processing = Context::get('processing', []);
        if ($file !== null) {
            $processing = array_replace($processing, [
                'file_id' => $file->id, 'file_guid' => $file->guid, 'user_id' => $file->user_id,
                'file_type' => $file->file_type, 'extension' => $file->fileExtension,
                'generation' => $file->meta['processing_generation'] ?? null,
            ]);
        }
        Context::scope(function () use ($exception): void {
            Nightwatch::sample(1.0);
            $this->exceptions->report($exception);
        }, data: ['processing' => $processing, 'processing_failure' => $failureContext, 'processing_stage' => $stage]);
    }
}
