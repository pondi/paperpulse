<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class ValidatedAIService implements AIService
{
    protected AIService $provider;

    protected OutputValidationService $validator;

    protected RetryService $retryService;

    protected ?ModelConfiguration $modelConfig;

    public function __construct(AIService $provider, ?ModelConfiguration $modelConfig = null)
    {
        $this->provider = $provider;
        $this->modelConfig = $modelConfig;
        $this->validator = app(OutputValidationService::class);
        $this->retryService = app(RetryService::class);
    }

    public function analyzeReceipt(string $content, array $options = []): array
    {
        return $this->executeWithValidation(
            fn () => $this->provider->analyzeReceipt($content, $options),
            'receipt',
            $options
        );
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        return $this->executeWithValidation(
            fn () => $this->provider->analyzeDocument($content, $options),
            'document',
            $options
        );
    }

    protected function executeWithValidation(callable $operation, string $type, array $options = []): array
    {
        try {
            return $this->retryService->execute(function ($attempt) use ($operation, $type, $options) {
                $result = $operation();

                if (! $result['success']) {
                    throw new \Exception($result['error'] ?? 'AI operation failed');
                }

                // Validate the output
                $validation = $this->validator->validateOutput(
                    $result['data'],
                    $type,
                    $options['schema'] ?? [],
                    $options
                );

                if (! $validation->isValid) {
                    Log::warning('[ValidatedAIService] Validation failed', [
                        'type' => $type,
                        'attempt' => $attempt + 1,
                        'errors' => $validation->errors,
                        'provider' => $this->provider->getProviderName(),
                    ]);

                    throw new \Exception('Validation failed: '.implode(', ', $validation->errors));
                }

                // Add validation metadata to result
                $result['validation'] = [
                    'passed' => true,
                    'warnings' => $validation->warnings,
                    'metadata' => $validation->metadata,
                ];

                // Use validated/sanitized data
                $result['data'] = $validation->data;

                if ($validation->hasWarnings()) {
                    Log::info('[ValidatedAIService] Validation passed with warnings', [
                        'type' => $type,
                        'warnings' => $validation->warnings,
                        'provider' => $this->provider->getProviderName(),
                    ]);
                }

                return $result;

            }, array_merge($options, ['operation_type' => $type]));

        } catch (\Exception $e) {
            Log::error('[ValidatedAIService] Operation failed after all retries', [
                'type' => $type,
                'error' => $e->getMessage(),
                'provider' => $this->provider->getProviderName(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $this->provider->getProviderName(),
                'validation' => [
                    'passed' => false,
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    // Delegate other methods to the wrapped provider
    public function extractMerchant(string $content): array
    {
        return $this->provider->extractMerchant($content);
    }

    

    public function generateSummary(string $content, int $maxLength = 200): string
    {
        return $this->provider->generateSummary($content, $maxLength);
    }

    public function suggestTags(string $content, int $maxTags = 5): array
    {
        return $this->provider->suggestTags($content, $maxTags);
    }

    public function classifyDocumentType(string $content): string
    {
        return $this->provider->classifyDocumentType($content);
    }

    public function extractEntities(string $content, array $types = []): array
    {
        return $this->provider->extractEntities($content, $types);
    }

    public function getProviderName(): string
    {
        return $this->provider->getProviderName().'_validated';
    }
}
