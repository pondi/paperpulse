<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Contracts\Services\ReceiptParserContract;
use App\Models\File;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Services\Factories\Concerns\ResolvesMerchant;
use App\Services\Receipt\ReceiptEnricherService;
use App\Services\Receipts\Analysis\CategoryResolver;
use App\Services\Receipts\Analysis\ReceiptProcessingPolicy;
use App\Services\Receipts\Analysis\UserPreferencesLoader;
use App\Services\Receipts\LineItemsCreator;
use App\Services\Receipts\TotalsCalculator;
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
        $prefs = UserPreferencesLoader::load($file->user_id, $file);
        $totals = TotalsCalculator::calculate($data['items'] ?? [], $data, app(ReceiptParserContract::class));
        $merchantId = $data['merchant_id'] ?? $this->resolveMerchantId($data, $file);
        $merchant = $merchantId ? Merchant::withoutGlobalScope('user')->where('user_id', $file->user_id)->find($merchantId) : null;
        [$categoryName, $categoryId] = CategoryResolver::resolve($data, $prefs['user'], $merchant, $this->merchantEnricher, $prefs['auto_categorize'], $prefs['default_category_id']);
        $data['total_reconciliation'] = $totals;
        ReceiptProcessingPolicy::applyReview($file, $totals);
        $receiptInfo = $data['receipt_info'] ?? [];
        $payment = $data['payment'] ?? [];
        $metadata = $data['metadata'] ?? [];

        return ReceiptProcessingPolicy::preserveUserValues(array_merge($data, [
            'merchant_id' => $merchantId,
            'category_id' => $categoryId,
            'receipt_category' => $categoryName,
            'receipt_date' => $receiptInfo['date'] ?? $data['receipt_date'] ?? null,
            'total_amount' => $totals['total_amount'] ?? $data['total_amount'] ?? 0,
            'tax_amount' => $totals['tax_amount'] ?? $data['tax_amount'] ?? 0,
            'currency' => $payment['currency'] ?? ($totals['currency'] ?? ($data['currency'] ?? $prefs['default_currency'])),
            'language' => $metadata['language'] ?? $data['language'] ?? null,
            'receipt_data' => $data,
        ]), $file);
    }

    protected function afterCreate(Model $model, array $data, File $file): void
    {
        $items = $data['items'] ?? [];
        $vendors = $data['vendors'] ?? [];

        if (UserPreferencesLoader::load($file->user_id, $file)['extract_line_items'] && ! empty($items)) {
            LineItemsCreator::create($model, $items, $vendors);
        }
    }
}
