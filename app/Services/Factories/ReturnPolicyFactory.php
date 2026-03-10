<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\ReturnPolicy;
use App\Services\Factories\Concerns\ChecksDataPresence;
use App\Services\Factories\Concerns\ResolvesMerchant;
use App\Services\Receipt\ReceiptEnricherService;

class ReturnPolicyFactory extends BaseEntityFactory
{
    use ChecksDataPresence;
    use ResolvesMerchant;

    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    protected function modelClass(): string
    {
        return ReturnPolicy::class;
    }

    protected function fields(): array
    {
        return [
            'receipt_id',
            'invoice_id',
            'merchant_id',
            'return_deadline',
            'exchange_deadline',
            'conditions',
            'refund_method',
            'restocking_fee',
            'restocking_fee_percentage',
            'is_final_sale',
            'requires_receipt',
            'requires_original_packaging',
        ];
    }

    protected function dateFields(): array
    {
        return ['return_deadline', 'exchange_deadline'];
    }

    protected function defaults(): array
    {
        return [
            'is_final_sale' => false,
            'requires_receipt' => true,
            'requires_original_packaging' => false,
        ];
    }

    protected function rawDataField(): ?string
    {
        return 'policy_data';
    }

    protected function shouldCreate(array $data): bool
    {
        $hasStructuredData = $this->hasAny($data, ['return_deadline', 'exchange_deadline', 'conditions', 'refund_method']);
        $hasUnstructuredData = ! empty($data['description']) || ! empty($data['policy']);

        return $hasStructuredData || $hasUnstructuredData;
    }

    protected function prepareData(array $data, File $file): array
    {
        $data['conditions'] = $data['conditions'] ?? $data['description'] ?? $data['policy'] ?? null;

        if (empty($data['merchant_id'])) {
            $data['merchant_id'] = $this->resolveMerchantId($data, $file);
        }

        return $data;
    }
}
