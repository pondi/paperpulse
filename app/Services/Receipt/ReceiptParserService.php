<?php

namespace App\Services\Receipt;

use App\Contracts\Services\ReceiptParserContract;
use App\Services\AI\AIService;
use App\Services\AI\AIServiceFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReceiptParserService implements ReceiptParserContract
{
    private AIService $aiService;

    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService ?? AIServiceFactory::create();
    }

    /**
     * Parse receipt content using AI
     */
    public function parseReceipt(string $content, int $fileId): array
    {
        $debugEnabled = config('app.debug');

        Log::info('[ReceiptParser] Starting receipt parsing', [
            'file_id' => $fileId,
            'content_length' => strlen($content),
        ]);

        if ($debugEnabled) {
            Log::debug('[ReceiptParser] Content preview', [
                'file_id' => $fileId,
                'content_preview' => substr($content, 0, 300).'...',
            ]);
        }

        try {
            $analysis = $this->aiService->analyzeReceipt($content);

            if ($debugEnabled) {
                Log::debug('[ReceiptParser] AI service response', [
                    'file_id' => $fileId,
                    'analysis_success' => $analysis['success'] ?? false,
                    'provider' => $analysis['provider'] ?? 'unknown',
                    'model' => $analysis['model'] ?? 'unknown',
                    'tokens_used' => $analysis['tokens_used'] ?? 0,
                    'fallback_used' => $analysis['fallback_used'] ?? false,
                    'template' => $analysis['template'] ?? 'unknown',
                    'data_keys' => isset($analysis['data']) && is_array($analysis['data']) ? array_keys($analysis['data']) : 'no data',
                    'error' => $analysis['error'] ?? null,
                ]);
            }

            if (! $analysis['success']) {
                throw new \Exception($analysis['error'] ?? 'Receipt analysis failed');
            }

            return $analysis;
        } catch (\Exception $e) {
            Log::error('[ReceiptParser] Parsing failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            throw $e;
        }
    }

    /**
     * Extract merchant information from parsed data
     */
    public function extractMerchantData(array $data): array
    {
        if (! empty($data['merchant'])) {
            return $data['merchant'];
        }

        if (! empty($data['store'])) {
            return [
                'name' => $data['store']['name'] ?? '',
                'address' => $data['store']['address'] ?? '',
                'vat_number' => $data['store']['organization_number'] ?? '',
            ];
        }

        return [
            'name' => $data['merchant_name'] ?? $data['store_name'] ?? '',
            'address' => $data['merchant_address'] ?? $data['store_address'] ?? '',
            'vat_number' => $data['org_number'] ?? $data['organization_number'] ?? '',
        ];
    }

    /**
     * Extract date/time from parsed data
     */
    public function extractDateTime(array $data): ?Carbon
    {
        $date = null;
        $time = null;

        if (! empty($data['receipt_info'])) {
            $date = $data['receipt_info']['date'] ?? null;
            $time = $data['receipt_info']['time'] ?? null;
        } elseif (! empty($data['receipt']) && is_array($data['receipt'])) {
            $receiptData = $data['receipt'];
            $date = $receiptData['date'] ?? null;
            $time = $receiptData['time'] ?? null;
        } elseif (! empty($data['transaction'])) {
            $date = $data['transaction']['date'] ?? null;
            $time = $data['transaction']['time'] ?? null;
        } else {
            $date = $data['date'] ?? null;
            $time = $data['time'] ?? null;
        }

        return $this->parseDateTime($date, $time);
    }

    /**
     * Extract totals from parsed data
     */
    public function extractTotals(array $data): array
    {
        $totalAmount = 0;
        $taxAmount = 0;

        if (! empty($data['totals'])) {
            $totalAmount = $data['totals']['total_amount'] ??
                          $data['totals']['total'] ??
                          $data['totals']['gross_amount'] ?? 0;
            $taxAmount = $data['totals']['tax_amount'] ??
                        $data['totals']['vat_amount'] ??
                        $data['totals']['tax'] ?? 0;
        } elseif (! empty($data['receipt']) && is_array($data['receipt'])) {
            $receiptData = $data['receipt'];
            $totalAmount = $receiptData['total'] ?? 0;

            if (! empty($receiptData['vat']) && is_array($receiptData['vat'])) {
                foreach ($receiptData['vat'] as $vatEntry) {
                    if (isset($vatEntry['vat_amount']) && is_numeric($vatEntry['vat_amount'])) {
                        $taxAmount += (float) $vatEntry['vat_amount'];
                    }
                }
            }
        } else {
            $totalAmount = $data['total_amount'] ?? $data['total'] ?? 0;
            $taxAmount = $data['tax_amount'] ?? $data['vat_amount'] ?? 0;
        }

        return [
            'total_amount' => (float) $totalAmount,
            'tax_amount' => (float) $taxAmount,
        ];
    }

    /**
     * Extract currency from parsed data
     */
    public function extractCurrency(array $data, string $default = 'NOK'): string
    {
        return $data['payment']['currency'] ??
               $data['currency'] ??
               $data['totals']['currency'] ??
               $default;
    }

    /**
     * Extract line items from parsed data
     */
    public function extractItems(array $data): array
    {
        if (! empty($data['receipt']) && is_array($data['receipt']) && ! empty($data['receipt']['items'])) {
            return $data['receipt']['items'];
        }

        return $data['items'] ?? $data['line_items'] ?? [];
    }

    /**
     * Extract merchant information directly from content
     */
    public function extractMerchantInfo(string $content): array
    {
        return $this->aiService->extractMerchant($content);
    }

    /**
     * Generate description from parsed data
     */
    public function generateDescription(array $data, string $defaultCurrency = 'NOK'): string
    {
        $parts = [];

        if (! empty($data['merchant']['name'])) {
            $parts[] = 'Purchase at '.$data['merchant']['name'];
        }

        if (! empty($data['items'])) {
            $parts[] = count($data['items']).' items';
        }

        if (! empty($data['totals']['total_amount']) || ! empty($data['totals']['total'])) {
            $total = $data['totals']['total_amount'] ?? $data['totals']['total'];
            $currency = $data['payment']['currency'] ?? $data['currency'] ?? $defaultCurrency;
            $parts[] = 'Total: '.number_format($total, 2).' '.$currency;
        }

        return implode(', ', $parts) ?: 'Receipt';
    }

    /**
     * Parse date and time strings into Carbon object
     */
    protected function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        try {
            if ($date) {
                $dateTime = Carbon::createFromFormat('Y-m-d', $date);
                if ($time) {
                    $timePart = Carbon::createFromFormat('H:i:s', $time);
                    $dateTime->setTimeFromTimeString($timePart->format('H:i:s'));
                }

                return $dateTime;
            }
        } catch (\Exception $e) {
            Log::warning('[ReceiptParser] Failed to parse date/time', [
                'date' => $date,
                'time' => $time,
                'error' => $e->getMessage(),
            ]);
        }

        Log::warning('[ReceiptParser] No valid date found in receipt data', [
            'date' => $date,
            'time' => $time,
        ]);

        return null;
    }
}
