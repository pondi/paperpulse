<?php

namespace App\Services\OCR;

use App\Services\OCR\Providers\TextractProvider;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OCRServiceFactory
{
    private static array $instances = [];

    /**
     * Create an OCR service instance
     */
    public static function create(?string $provider = null): OCRService
    {
        $provider = $provider ?? config('ai.ocr.provider', 'textract');

        // Return cached instance if available
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        Log::info('Creating OCR provider instance', ['provider' => $provider]);

        $instance = match ($provider) {
            'textract' => app(TextractProvider::class),
            default => throw new InvalidArgumentException("Unsupported OCR provider: {$provider}")
        };

        // Cache the instance
        self::$instances[$provider] = $instance;

        return $instance;
    }

    /**
     * Create with automatic provider selection
     */
    public static function createForFile(string $filePath, array $providers = []): OCRService
    {
        $providers = empty($providers) ? [config('ai.ocr.provider', 'textract')] : $providers;
        foreach ($providers as $providerName) {
            $provider = self::create($providerName);
            if ($provider->canHandle($filePath)) {
                return $provider;
            }
        }

        throw new InvalidArgumentException('No configured OCR provider supports this file.');
    }

    /**
     * Get all available providers
     */
    public static function getAvailableProviders(): array
    {
        return ['textract'];
    }

    /**
     * Check if provider is available
     */
    public static function isProviderAvailable(string $provider): bool
    {
        try {
            $service = self::create($provider);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clear cached instances
     */
    public static function clearCache(): void
    {
        self::$instances = [];
    }
}
