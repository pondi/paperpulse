<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AIService;
use App\Services\AI\ResilienceService;
use Exception;
use Illuminate\Support\Facades\Log;

class CircuitBreakerProvider implements AIService
{
    protected AIService $provider;

    protected ResilienceService $resilience;

    protected string $providerName;

    public function __construct(AIService $provider, ResilienceService $resilience)
    {
        $this->provider = $provider;
        $this->resilience = $resilience;
        $this->providerName = $provider->getProviderName();
    }

    public function analyzeReceipt(string $content, array $options = []): array
    {
        try {
            return $this->provider->analyzeReceipt($content, $options);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Receipt analysis failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);
            throw $e;
        }
    }

    public function analyzeDocument(string $content, array $options = []): array
    {
        try {
            return $this->provider->analyzeDocument($content, $options);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Document analysis failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);
            throw $e;
        }
    }

    public function extractMerchant(string $content): array
    {
        try {
            return $this->provider->extractMerchant($content);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Merchant extraction failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return [];
        }
    }

    public function extractLineItems(string $content): array
    {
        try {
            return $this->provider->extractLineItems($content);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Line items extraction failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return [];
        }
    }

    public function generateSummary(string $content, int $maxLength = 200): string
    {
        try {
            return $this->provider->generateSummary($content, $maxLength);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Summary generation failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return 'Summary generation failed';
        }
    }

    public function suggestTags(string $content, int $maxTags = 5): array
    {
        try {
            return $this->provider->suggestTags($content, $maxTags);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Tag suggestion failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return [];
        }
    }

    public function classifyDocumentType(string $content): string
    {
        try {
            return $this->provider->classifyDocumentType($content);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Document classification failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return 'other';
        }
    }

    public function extractEntities(string $content, array $types = []): array
    {
        try {
            return $this->provider->extractEntities($content, $types);
        } catch (Exception $e) {
            Log::warning('[CircuitBreakerProvider] Entity extraction failed', [
                'error' => $e->getMessage(),
                'provider' => $this->providerName,
            ]);

            return array_fill_keys($types ?: ['people', 'organizations', 'locations'], []);
        }
    }

    public function getProviderName(): string
    {
        return $this->provider->getProviderName().'_resilient';
    }
}
