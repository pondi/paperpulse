<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Voucher;
use App\Services\Factories\Concerns\ChecksDataPresence;
use App\Services\Factories\Concerns\ResolvesMerchant;
use App\Services\Receipt\ReceiptEnricherService;

class VoucherFactory extends BaseEntityFactory
{
    use ChecksDataPresence;
    use ResolvesMerchant;

    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    protected function modelClass(): string
    {
        return Voucher::class;
    }

    protected function fields(): array
    {
        return [
            'merchant_id',
            'voucher_type',
            'code',
            'barcode',
            'qr_code',
            'issue_date',
            'expiry_date',
            'original_value',
            'current_value',
            'currency',
            'installment_count',
            'monthly_payment',
            'first_payment_date',
            'final_payment_date',
            'is_redeemed',
            'redeemed_at',
            'redemption_location',
            'terms_and_conditions',
            'restrictions',
        ];
    }

    protected function dateFields(): array
    {
        return ['issue_date', 'expiry_date', 'first_payment_date', 'final_payment_date', 'redeemed_at'];
    }

    protected function defaults(): array
    {
        return [
            'voucher_type' => 'gift_card',
            'currency' => 'NOK',
            'is_redeemed' => false,
        ];
    }

    protected function rawDataField(): ?string
    {
        return 'voucher_data';
    }

    protected function shouldCreate(array $data): bool
    {
        return $this->hasAny($data, ['code', 'barcode', 'qr_code', 'original_value', 'current_value']);
    }

    protected function prepareData(array $data, File $file): array
    {
        if (empty($data['merchant_id'])) {
            $data['merchant_id'] = $this->resolveMerchantId($data, $file);
        }

        return $data;
    }
}
