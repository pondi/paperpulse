<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Receipt;
use App\Services\Receipt\ReceiptEnricherService;
use App\Services\Receipts\LineItemsCreator;

class ReceiptFactory
{
    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    public function create(array $data, File $file): ?Receipt
    {
        if (empty($data)) {
            return null;
        }

        $totals = $data['totals'] ?? [];
        $receiptInfo = $data['receipt_info'] ?? [];
        $items = $data['items'] ?? [];
        $vendors = $data['vendors'] ?? [];
        $payment = $data['payment'] ?? [];
        $metadata = $data['metadata'] ?? [];

        $merchantId = $data['merchant_id'] ?? $this->resolveMerchantId($data, $file);
        $currency = $payment['currency'] ?? ($totals['currency'] ?? 'NOK');
        $categoryId = $data['category_id'] ?? null;

        $receipt = Receipt::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'merchant_id' => $merchantId,
            'category_id' => $categoryId,
            'receipt_date' => $receiptInfo['date'] ?? null,
            'total_amount' => $totals['total_amount'] ?? 0,
            'tax_amount' => $totals['tax_amount'] ?? 0,
            'currency' => $currency,
            'receipt_category' => $data['receipt_category'] ?? null,
            'receipt_description' => $data['receipt_description'] ?? null,
            'tags' => $data['tags'] ?? null,
            'ai_entities' => $data['ai_entities'] ?? null,
            'language' => $metadata['language'] ?? null,
            'receipt_data' => json_encode($data),
            'note' => $data['note'] ?? null,
        ]);

        if (! empty($items)) {
            LineItemsCreator::create($receipt, $items, $vendors);
        }

        return $receipt;
    }

    public function resolveMerchantId(array $data, File $file): ?int
    {
        $merchant = $data['merchant'] ?? [];

        if (empty($merchant) && ! empty($data['vendor'])) {
            $merchant = $data['vendor'];
        }

        if (empty($merchant) && isset($data['merchant_name'])) {
            $merchant = [
                'name' => $data['merchant_name'],
                'vat_number' => $data['merchant_vat'] ?? null,
                'address' => $data['merchant_address'] ?? null,
            ];
        }

        if (empty($merchant['name'])) {
            return null;
        }

        $merchantModel = $this->merchantEnricher->findOrCreateMerchant([
            'name' => $merchant['name'],
            'vat_number' => $merchant['vat_number'] ?? null,
            'address' => $merchant['address'] ?? null,
        ], $file->user_id);

        return $merchantModel?->id;
    }
}
