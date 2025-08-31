<?php

namespace App\Services\OCR;

use App\Services\OCR\Providers\TesseractProvider;
use App\Services\OCR\Providers\TextractProvider;
use App\Services\StorageService;
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
        $provider = $provider ?? config('ocr.provider', 'textract');

        // Return cached instance if available
        if (isset(self::$instances[$provider])) {
            return self::$instances[$provider];
        }

        Log::info('Creating OCR provider instance', ['provider' => $provider]);

        $instance = match ($provider) {
            'textract' => new TextractProvider(app(StorageService::class)),
            'tesseract' => new TesseractProvider,
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
        $providers = empty($providers) ? ['textract', 'tesseract'] : $providers;

        foreach ($providers as $providerName) {
            try {
                $provider = self::create($providerName);
                if ($provider->canHandle($filePath)) {
                    return $provider;
                }
            } catch (\Exception $e) {
                Log::debug("OCR provider {$providerName} cannot handle file", [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        // Fallback to default provider
        return self::create();
    }

    /**
     * Get all available providers
     */
    public static function getAvailableProviders(): array
    {
        return ['textract', 'tesseract'];
    }

    /**
     * Check if provider is available
     */
    public static function isProviderAvailable(string $provider): bool
    {
        try {
            $service = self::create($provider);

            return true;
        } catch (\Exception $e) {
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
