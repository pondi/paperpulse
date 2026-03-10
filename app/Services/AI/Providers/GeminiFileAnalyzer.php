<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Exceptions\GeminiApiException;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf;

/**
 * Handles file validation, context building, and large file handling for Gemini.
 */
class GeminiFileAnalyzer
{
    /**
     * Validate file size and mime support for Gemini.
     */
    public function ensureSupported(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new GeminiApiException(
                "File not found: {$filePath}",
                GeminiApiException::CODE_FILE_NOT_FOUND,
                false,
                ['file_path' => $filePath]
            );
        }

        $fileSize = filesize($filePath) ?: 0;
        $maxSizeMb = (int) config('ai.providers.gemini.max_file_size_mb', 50);
        if ($fileSize > ($maxSizeMb * 1024 * 1024)) {
            throw new GeminiApiException(
                "Gemini processing supports files up to {$maxSizeMb}MB. Please upload a smaller file or switch providers.",
                GeminiApiException::CODE_FILE_TOO_LARGE,
                false,
                [
                    'file_size_bytes' => $fileSize,
                    'max_size_mb' => $maxSizeMb,
                ]
            );
        }

        $mime = mime_content_type($filePath) ?: '';
        $supported = config('ai.providers.gemini.supported_mime_types', []);
        if (! empty($supported) && ! in_array($mime, $supported)) {
            throw new GeminiApiException(
                "Unsupported mime type for Gemini: {$mime}",
                GeminiApiException::CODE_UNSUPPORTED_MIME,
                false,
                [
                    'mime' => $mime,
                    'supported_mime_types' => $supported,
                ]
            );
        }
    }

    /**
     * Build a text input context for plain text files.
     */
    public function buildTextContext(string $filePath, string $mime, string $extension): ?array
    {
        if (! $this->isTextFile($mime, $extension)) {
            return null;
        }

        $maxBytes = (int) config('ai.providers.gemini.text_max_bytes', 200000);
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        $content = stream_get_contents($handle, $maxBytes + 1);
        fclose($handle);

        if ($content === false) {
            return null;
        }

        $truncated = strlen($content) > $maxBytes;
        if ($truncated) {
            $content = substr($content, 0, $maxBytes);
        }

        return [
            'excerpt' => $content,
            'bytes' => strlen($content),
            'truncated' => $truncated,
        ];
    }

    /**
     * Build a context payload for large files to guide downstream handling.
     */
    public function buildLargeFileContext(string $filePath, string $mime, int $fileSize, ?array $textContext): ?array
    {
        $maxSizeMb = (int) config('ai.providers.gemini.max_file_size_mb', 50);
        $defaultThreshold = max(1, (int) floor($maxSizeMb * 0.8));
        $thresholdMb = (int) config('ai.providers.gemini.large_file_threshold_mb', $defaultThreshold);
        if ($thresholdMb <= 0) {
            $thresholdMb = $defaultThreshold;
        }
        $thresholdMb = min($thresholdMb, $maxSizeMb);
        $thresholdBytes = $thresholdMb * 1024 * 1024;
        $isLargeBySize = $fileSize >= $thresholdBytes;

        $context = [
            'size_bytes' => $fileSize,
            'size_mb' => round($fileSize / (1024 * 1024), 2),
            'threshold_mb' => $thresholdMb,
            'max_size_mb' => $maxSizeMb,
        ];

        $strategy = null;

        if ($textContext && ($textContext['truncated'] ?? false)) {
            $strategy = 'text_truncate';
            $context['text_bytes'] = $textContext['bytes'] ?? null;
            $context['text_truncated'] = true;
        }

        if ($mime === 'application/pdf') {
            $pageCount = $this->getPdfPageCount($filePath);
            $context['page_count'] = $pageCount;

            $pageLimit = (int) config('ai.providers.gemini.large_pdf_page_limit', 25);
            if ($pageLimit <= 0) {
                $pageLimit = 25;
            }
            if ($pageCount > $pageLimit) {
                $sampleSize = (int) config('ai.providers.gemini.large_pdf_sample_size', 4);
                if ($sampleSize <= 0) {
                    $sampleSize = 4;
                }
                $context['page_limit'] = $pageLimit;
                $context['sample_pages'] = $this->buildPageSample($pageCount, $sampleSize);
                $context['sample_size'] = $sampleSize;
                $strategy = $strategy ?? 'sample_pages';
            }
        }

        if (! $isLargeBySize && $strategy === null) {
            return null;
        }

        $context['strategy'] = $strategy ?? 'size_only';

        return $context;
    }

    /**
     * Build the Gemini prompt.
     */
    public function buildPrompt(?string $prompt, array $schema, ?array $textContext, ?array $largeFileContext): string
    {
        $base = trim($prompt ?? '');

        $lines = array_filter([
            $base,
        ]);

        if ($textContext) {
            $lines[] = "\n--- Text excerpt for reference (if needed) ---";
            $lines[] = $textContext['excerpt'] ?? '';
        }

        if ($largeFileContext) {
            $lines[] = "\n--- Large file context ---";
            $lines[] = 'This is a '.($largeFileContext['total_pages'] ?? '?').' page document. Focus on extracting complete and accurate information.';
        }

        return trim(implode("\n\n", $lines));
    }

    /**
     * Build parts payload for Gemini request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildParts(string $promptText, string $mime, string $fileContent, ?array $textContext): array
    {
        $parts = [
            [
                'text' => $promptText,
            ],
            [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => base64_encode($fileContent),
                ],
            ],
        ];

        if ($textContext && ! empty($textContext['excerpt'])) {
            $parts[] = [
                'text' => 'Text excerpt for reference: '.$textContext['excerpt'],
            ];
        }

        return $parts;
    }

    /**
     * Determine whether a file should be treated as text input.
     */
    protected function isTextFile(string $mime, string $extension): bool
    {
        if (str_starts_with($mime, 'text/')) {
            return true;
        }

        return in_array($extension, ['txt', 'md', 'csv', 'log'], true);
    }

    /**
     * Get the page count of a PDF file.
     */
    protected function getPdfPageCount(string $filePath): int
    {
        try {
            if (! extension_loaded('imagick')) {
                Log::debug('[GeminiFileAnalyzer] Imagick not available for PDF page counting, assuming single page');

                return 1;
            }

            $gsPath = exec('which gs 2>/dev/null');
            if (empty($gsPath)) {
                Log::debug('[GeminiFileAnalyzer] Ghostscript not available for PDF page counting, assuming single page');

                return 1;
            }

            $pdf = new Pdf($filePath);
            $pageCount = $pdf->pageCount();

            Log::debug('[GeminiFileAnalyzer] PDF page count determined', [
                'file_path' => basename($filePath),
                'page_count' => $pageCount,
            ]);

            return $pageCount;

        } catch (Exception $e) {
            Log::warning('[GeminiFileAnalyzer] Failed to determine PDF page count, assuming single page', [
                'file_path' => basename($filePath),
                'error' => $e->getMessage(),
            ]);

            return 1;
        }
    }

    /**
     * Build a sample of pages for large PDFs (first + last pages).
     *
     * @return array<int>
     */
    protected function buildPageSample(int $pageCount, int $sampleSize): array
    {
        $sampleSize = max(1, $sampleSize);
        $startPages = range(1, min($sampleSize, $pageCount));
        $endPages = range(max(1, $pageCount - $sampleSize + 1), $pageCount);
        $pages = array_values(array_unique(array_merge($startPages, $endPages)));
        sort($pages);

        return $pages;
    }
}
