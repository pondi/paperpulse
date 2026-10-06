<?php

namespace App\Listeners;

use App\Exceptions\AIResponseException;
use App\Exceptions\GeminiApiException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Laravel\Nightwatch\Facades\Nightwatch;

class ReportFailedJob
{
    public function __construct(private ExceptionHandler $exceptions) {}

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        Context::add('queue_failure', [
            'job_class' => $event->job->resolveName(),
            'job_uuid' => $event->job->uuid(),
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempt' => $event->job->attempts(),
        ]);

        $exception = $event->exception;
        if ($exception instanceof AIResponseException || $exception instanceof GeminiApiException) {
            $context = $exception instanceof AIResponseException ? $exception->context : $exception->getContext();
            Context::add('processing_failure', [
                'soft' => false,
                'stage' => Context::get('processing_stage', $event->job->resolveName()),
                'exception_class' => $exception::class,
                'code' => $exception instanceof AIResponseException ? $exception->errorCode : $exception->getErrorCode(),
                'retryable' => $exception instanceof AIResponseException ? $exception->retryable : $exception->isRetryable(),
                ...Arr::only($context, [
                    'field', 'errors', 'dates', 'stage', 'status', 'provider_attempts',
                    'run_calls', 'daily_calls', 'run_reserved_tokens', 'daily_reserved_tokens', 'requested_tokens',
                ]),
            ]);
        }

        Nightwatch::sample(1.0);
        $this->exceptions->report($exception);
    }
}
