<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Receipt;
use App\Services\Factories\Concerns\ResolvesMerchant;
use App\Services\Receipt\ReceiptEnricherService;
use App\Services\Receipts\LineItemsCreator;
use Illuminate\Database\Eloquent\Model;

class ReceiptFactory extends BaseEntityFactory
{
    use ResolvesMerchant;

    public function __construct(
        protected ReceiptEnricherService $merchantEnricher,
    ) {}

    protected function modelClass(): string
    {
        return Receipt::class;
    }

    protected function fields(): array
    {
        return [
            'merchant_id',
            'category_id',
            'receipt_date',
            'total_amount',
            'tax_amount',
            'currency',
            'receipt_category',
            'receipt_description',
            'tags',
            'ai_entities',
            'language',
            'receipt_data',
            'note',
        ];
    }

    protected function dateFields(): array
    {
        return ['receipt_date'];
    }

    protected function defaults(): array
    {
        return [
            'total_amount' => 0,
            'tax_amount' => 0,
        ];
    }

    protected function prepareData(array $data, File $file): array
    {
        $totals = $data['totals'] ?? [];
        $receiptInfo = $data['receipt_info'] ?? [];
        $payment = $data['payment'] ?? [];
        $metadata = $data['metadata'] ?? [];

        return array_merge($data, [
            'merchant_id' => $data['merchant_id'] ?? $this->resolveMerchantId($data, $file),
            'receipt_date' => $receiptInfo['date'] ?? $data['receipt_date'] ?? null,
            'total_amount' => $totals['total_amount'] ?? $data['total_amount'] ?? 0,
            'tax_amount' => $totals['tax_amount'] ?? $data['tax_amount'] ?? 0,
            'currency' => $payment['currency'] ?? ($totals['currency'] ?? ($data['currency'] ?? 'NOK')),
            'language' => $metadata['language'] ?? $data['language'] ?? null,
            'receipt_data' => json_encode($data),
        ]);
    }

    protected function afterCreate(Model $model, array $data, File $file): void
    {
        $items = $data['items'] ?? [];
        $vendors = $data['vendors'] ?? [];

        if (! empty($items)) {
            LineItemsCreator::create($model, $items, $vendors);
        }
    }
}
