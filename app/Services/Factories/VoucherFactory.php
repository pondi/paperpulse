<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Voucher;
use App\Services\Factories\Concerns\ChecksDataPresence;
use App\Services\Receipt\ReceiptEnricherService;

class VoucherFactory
{
    use ChecksDataPresence;

    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    public function create(array $data, File $file): ?Voucher
    {
        $hasIdentifier = $this->hasAny($data, ['code', 'barcode', 'qr_code', 'original_value', 'current_value']);

        if (! $hasIdentifier) {
            return null;
        }

        return Voucher::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'merchant_id' => $data['merchant_id'] ?? $this->resolveMerchantId($data, $file),
            'voucher_type' => $data['voucher_type'] ?? 'gift_card',
            'code' => $data['code'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'original_value' => $data['original_value'] ?? null,
            'current_value' => $data['current_value'] ?? null,
            'currency' => $data['currency'] ?? 'NOK',
            'installment_count' => $data['installment_count'] ?? null,
            'monthly_payment' => $data['monthly_payment'] ?? null,
            'first_payment_date' => $data['first_payment_date'] ?? null,
            'final_payment_date' => $data['final_payment_date'] ?? null,
            'is_redeemed' => $data['is_redeemed'] ?? false,
            'redeemed_at' => $data['redeemed_at'] ?? null,
            'redemption_location' => $data['redemption_location'] ?? null,
            'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
            'restrictions' => $data['restrictions'] ?? null,
            'voucher_data' => $data['voucher_data'] ?? $data,
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
