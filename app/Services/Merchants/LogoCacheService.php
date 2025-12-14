<?php

namespace App\Services\Merchants;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Imagick;

/**
 * Handles caching and storage of generated merchant logos.
 * Single responsibility: Manage logo caching and retrieval.
 */
class LogoCacheService
{
    private const CACHE_PREFIX = 'merchant_logo_';

    private const CACHE_TTL = 60 * 60 * 24 * 30; // 30 days

    private const STORAGE_PATH = 'merchant-logos';

    /**
     * Get or generate logo for merchant.
     */
    public static function getOrGenerate(int $merchantId, string $merchantName): string
    {
        $cacheKey = self::CACHE_PREFIX.$merchantId;

        // Try to get from cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Generate new logo
        $svg = LogoGenerator::generateSvg($merchantName);

        // Cache the SVG content
        Cache::put($cacheKey, $svg, self::CACHE_TTL);

        return $svg;
    }

    /**
     * Store logo as PNG file for better performance.
     *
     * @return string Path to stored file
     */
    public static function storeAsPng(int $merchantId, string $merchantName): string
    {
        $filename = self::STORAGE_PATH.'/'.$merchantId.'.png';

        // Check if already exists
        if (Storage::disk('public')->exists($filename)) {
            return Storage::disk('public')->url($filename);
        }

        // Generate SVG
        $svg = LogoGenerator::generateSvg($merchantName);

        // Convert to PNG using Imagick if available
        if (extension_loaded('imagick')) {
            $image = new Imagick;
            $image->readImageBlob($svg);
            $image->setImageFormat('png');

            Storage::disk('public')->put($filename, $image->getImageBlob());

            return Storage::disk('public')->url($filename);
        }

        // Otherwise return data URL with SVG
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Clear logo cache for merchant.
     */
    public static function clearCache(int $merchantId): void
    {
        Cache::forget(self::CACHE_PREFIX.$merchantId);

        // Also remove stored file if exists
        $filename = self::STORAGE_PATH.'/'.$merchantId.'.png';
        if (Storage::disk('public')->exists($filename)) {
            Storage::disk('public')->delete($filename);
        }
    }
}
