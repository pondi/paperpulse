<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\ReturnPolicy;
use App\Services\Factories\Concerns\ChecksDataPresence;
use App\Services\Receipt\ReceiptEnricherService;

class ReturnPolicyFactory
{
    use ChecksDataPresence;

    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    public function create(array $data, File $file): ?ReturnPolicy
    {
        $hasStructuredData = $this->hasAny($data, ['return_deadline', 'exchange_deadline', 'conditions', 'refund_method']);
        $hasUnstructuredData = ! empty($data['description']) || ! empty($data['policy']);

        if (! $hasStructuredData && ! $hasUnstructuredData) {
            return null;
        }

        $conditions = $data['conditions'] ?? $data['description'] ?? $data['policy'] ?? null;

        return ReturnPolicy::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'receipt_id' => $data['receipt_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? $this->resolveMerchantId($data, $file),
            'return_deadline' => $data['return_deadline'] ?? null,
            'exchange_deadline' => $data['exchange_deadline'] ?? null,
            'conditions' => $conditions,
            'refund_method' => $data['refund_method'] ?? null,
            'restocking_fee' => $data['restocking_fee'] ?? null,
            'restocking_fee_percentage' => $data['restocking_fee_percentage'] ?? null,
            'is_final_sale' => $data['is_final_sale'] ?? false,
            'requires_receipt' => $data['requires_receipt'] ?? true,
            'requires_original_packaging' => $data['requires_original_packaging'] ?? false,
            'policy_data' => $data['policy_data'] ?? $data,
        ]);
    }

    protected function resolveMerchantId(array $data, File $file): ?int
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
