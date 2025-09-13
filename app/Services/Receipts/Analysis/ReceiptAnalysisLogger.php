<?php

namespace App\Services\Receipts\Analysis;

use Illuminate\Support\Facades\Log;

class ReceiptAnalysisLogger
{
    public static function start(int $fileId, int $userId, string $content, ?array $structuredData = null): void
    {
        Log::info('[ReceiptAnalysis] Starting receipt analysis', [
            'file_id' => $fileId,
            'user_id' => $userId,
            'content_length' => strlen($content),
            'forms_count' => count($structuredData['forms'] ?? []),
            'tables_count' => count($structuredData['tables'] ?? []),
        ]);
    }

    public static function preferences(int $fileId, array $prefs): void
    {
        Log::debug('[ReceiptAnalysis] Preferences', [
            'file_id' => $fileId,
            'default_currency' => $prefs['default_currency'] ?? null,
            'auto_categorize' => $prefs['auto_categorize'] ?? null,
            'extract_line_items' => $prefs['extract_line_items'] ?? null,
            'default_category_id' => $prefs['default_category_id'] ?? null,
        ]);
    }

    public static function dataValidated(int $fileId, array $warnings = []): void
    {
        Log::debug('[ReceiptAnalysis] Data validated and sanitized', [
            'file_id' => $fileId,
            'validation_warnings' => $warnings,
        ]);
    }

    public static function merchantProcessed(int $fileId, ?int $merchantId, ?string $merchantName): void
    {
        Log::debug('[ReceiptAnalysis] Merchant processed', compact('fileId') + [
            'merchant_id' => $merchantId,
            'merchant_name' => $merchantName,
        ]);
    }

    public static function creatingReceipt(int $fileId, array $payload): void
    {
        Log::debug('[ReceiptAnalysis] Creating receipt', [
            'file_id' => $fileId,
            'payload_keys' => array_keys($payload),
        ]);
    }

    public static function lineItemsCreated(int $receiptId, int $count): void
    {
        Log::debug('[ReceiptAnalysis] Line items created', [
            'receipt_id' => $receiptId,
            'items_count' => $count,
        ]);
    }

    public static function completed(int $receiptId, ?int $merchantId, int $itemCount, float $ms, ?array $structuredData = null): void
    {
        Log::info('[ReceiptAnalysis] Receipt analysis completed', [
            'receipt_id' => $receiptId,
            'merchant_id' => $merchantId,
            'item_count' => $itemCount,
            'forms_used' => count($structuredData['forms'] ?? []),
            'tables_used' => count($structuredData['tables'] ?? []),
            'processing_time_ms' => $ms,
        ]);
    }

    public static function failed(int $fileId, string $message, float $ms): void
    {
        Log::error('[ReceiptAnalysis] Receipt analysis failed', [
            'error' => $message,
            'file_id' => $fileId,
            'processing_time_ms' => $ms,
        ]);
    }
}

