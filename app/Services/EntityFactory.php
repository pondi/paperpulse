<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Services\Factories\BankStatementFactory;
use App\Services\Factories\ContractFactory;
use App\Services\Factories\DocumentFactory;
use App\Services\Factories\InvoiceFactory;
use App\Services\Factories\ReceiptFactory;
use App\Services\Factories\ReturnPolicyFactory;
use App\Services\Factories\VoucherFactory;
use App\Services\Factories\WarrantyFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

class EntityFactory
{
    public function __construct(
        protected DatabaseManager $db,
        protected ReceiptFactory $receiptFactory,
        protected VoucherFactory $voucherFactory,
        protected WarrantyFactory $warrantyFactory,
        protected ReturnPolicyFactory $returnPolicyFactory,
        protected InvoiceFactory $invoiceFactory,
        protected ContractFactory $contractFactory,
        protected BankStatementFactory $bankStatementFactory,
        protected DocumentFactory $documentFactory,
    ) {}

    /**
     * Create entities from parsed Gemini data.
     *
     * @return array<int, array{type:string, model:mixed}>
     */
    public function createEntitiesFromParsedData(array $parsedData, File $file, string $detectedType = 'document'): array
    {
        $entities = $parsedData['entities'] ?? [];
        $created = [];

        // Pre-resolve category IDs BEFORE the transaction to avoid PostgreSQL transaction abort issues.
        $resolvedCategories = $this->preResolveCategoryIds($entities, $file);

        $this->db->transaction(function () use ($entities, $file, &$created, $resolvedCategories) {
            $context = [];

            foreach ($entities as $entity) {
                if (! is_array($entity)) {
                    Log::warning('[EntityFactory] Skipped invalid entity (not an array)', [
                        'file_id' => $file->id,
                        'entity_sample' => is_scalar($entity) ? $entity : gettype($entity),
                    ]);

                    continue;
                }

                $type = strtolower($entity['type'] ?? 'document');
                $data = $entity['data'] ?? [];

                // Fix for Gemini sometimes flattening the data structure
                if (empty($data) && ! empty($entity) && count($entity) > 2) {
                    $possibleData = $entity;
                    unset($possibleData['type']);
                    unset($possibleData['confidence_score']);

                    $hasData = match ($type) {
                        'receipt' => isset($possibleData['merchant']) || isset($possibleData['totals']),
                        'voucher' => isset($possibleData['code']) || isset($possibleData['voucher_type']),
                        'warranty' => isset($possibleData['product_name']) || isset($possibleData['warranty_end_date']),
                        'return_policy' => isset($possibleData['return_deadline']) || isset($possibleData['conditions']),
                        default => false
                    };

                    if ($hasData) {
                        $data = $possibleData;
                        Log::info('[EntityFactory] Detected flattened data structure, repaired', [
                            'type' => $type,
                            'keys' => array_keys($data),
                        ]);
                    }
                }

                $confidence = $entity['confidence_score'] ?? null;

                // Inject parent IDs into child entities
                if (isset($context['receipt']) && in_array($type, ['voucher', 'warranty', 'return_policy'])) {
                    $data['receipt_id'] = $context['receipt']->id;
                }
                if (isset($context['invoice']) && in_array($type, ['voucher', 'warranty', 'return_policy', 'invoice_line_items'])) {
                    $data['invoice_id'] = $context['invoice']->id;
                }

                $model = $this->createEntity($type, $data, $file, $context, $resolvedCategories);

                // Update context for parent-child relationships
                if ($model && in_array($type, ['receipt', 'invoice', 'bank_statement']) && ! is_iterable($model)) {
                    $context[$type] = $model;
                }

                if ($model) {
                    if (is_iterable($model)) {
                        foreach ($model as $subModel) {
                            $created[] = ['type' => $type, 'model' => $subModel];
                            $this->createExtractableEntityRecord($file, $type, $subModel, count($created) === 1, $confidence);
                        }
                    } else {
                        $created[] = ['type' => $type, 'model' => $model];
                        $this->createExtractableEntityRecord($file, $type, $model, count($created) === 1, $confidence);
                    }

                    Log::info('[EntityFactory] Entity created successfully', [
                        'type' => $type,
                        'file_id' => $file->id,
                        'entity_class' => is_iterable($model) ? 'array' : get_class($model),
                        'entity_count' => is_iterable($model) ? count($model) : 1,
                        'confidence' => $confidence,
                    ]);
                } else {
                    Log::warning('[EntityFactory] Entity creation skipped', [
                        'type' => $type,
                        'file_id' => $file->id,
                        'user_id' => $file->user_id,
                        'reason' => 'validation_failed_or_empty_data',
                        'data_keys_provided' => array_keys($data),
                        'data_sample' => array_map(fn ($v) => is_array($v) ? '[array]' : (is_string($v) ? substr($v, 0, 50) : $v), $data),
                    ]);
                }
            }
        });

        // Track extraction summary on file for debugging
        $file->refresh();
        $file->meta = array_merge($file->meta ?? [], [
            'extraction_summary' => [
                'total_entities_detected' => count($entities),
                'total_entities_created' => count($created),
                'entities_created' => array_map(fn ($e) => [
                    'type' => $e['type'],
                    'id' => $e['model']->id ?? null,
                ], $created),
                'entities_skipped' => count($entities) - count($created),
                'extracted_at' => now()->toIso8601String(),
            ],
        ]);
        $file->save();

        return $created;
    }

    /**
     * Dispatch entity creation to the appropriate factory.
     */
    protected function createEntity(string $type, array $data, File $file, array $context, array $resolvedCategories): mixed
    {
        return match ($type) {
            'receipt' => $this->createReceipt($data, $file, $resolvedCategories),
            'voucher' => $this->voucherFactory->create($data, $file),
            'warranty' => $this->warrantyFactory->create($data, $file),
            'return_policy' => $this->returnPolicyFactory->create($data, $file),
            'invoice' => $this->invoiceFactory->create($data, $file),
            'invoice_line_items' => $this->createInvoiceLineItems($data, $context),
            'contract' => $this->contractFactory->create($data, $file),
            'bank_statement' => $this->bankStatementFactory->create($data, $file),
            'bank_transactions' => $this->createBankTransactions($data, $context),
            default => $this->documentFactory->create($data, $file, $type),
        };
    }

    protected function createReceipt(array $data, File $file, array $resolvedCategories): mixed
    {
        $categoryName = $data['receipt_category'] ?? null;
        if ($categoryName && isset($resolvedCategories[$categoryName])) {
            $data['category_id'] = $resolvedCategories[$categoryName];
        }

        if (empty($data['category_id'])) {
            $data['category_id'] = $this->resolveCategoryId($data, $file);
        }

        return $this->receiptFactory->create($data, $file);
    }

    protected function createInvoiceLineItems(array $data, array $context): mixed
    {
        if (empty($context['invoice'])) {
            return null;
        }

        $items = $data['items'] ?? $data['line_items'] ?? [];

        return $this->invoiceFactory->createLineItems($items, $context['invoice']);
    }

    protected function createBankTransactions(array $data, array $context): mixed
    {
        if (empty($context['bank_statement'])) {
            return null;
        }

        $transactions = $data['transactions'] ?? [];

        return $this->bankStatementFactory->createTransactions($transactions, $context['bank_statement']);
    }

    /**
     * Create an ExtractableEntity record to track the extraction.
     */
    protected function createExtractableEntityRecord(File $file, string $type, $model, bool $isPrimary, ?float $confidence = null): void
    {
        ExtractableEntity::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'entity_type' => $type,
            'entity_id' => $model->id,
            'is_primary' => $isPrimary,
            'confidence_score' => $confidence,
            'extraction_provider' => $file->processing_type ?? 'gemini',
            'extraction_model' => config('ai.providers.gemini.model', 'gemini-2.0-flash'),
            'extraction_metadata' => [
                'entity_type_name' => $type,
                'extracted_at' => now()->toIso8601String(),
                'confidence' => $confidence,
            ],
            'extracted_at' => now(),
        ]);
    }

    /**
     * Pre-resolve category IDs for all entities before the main transaction.
     *
     * @return array<string, int> Map of category name to ID
     */
    protected function preResolveCategoryIds(array $entities, File $file): array
    {
        $resolved = [];

        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $type = strtolower($entity['type'] ?? '');
            $data = $entity['data'] ?? $entity;

            if ($type === 'receipt') {
                $categoryName = $data['receipt_category'] ?? null;
                if ($categoryName && ! isset($resolved[$categoryName])) {
                    $categoryId = $this->resolveOrCreateCategory($categoryName, $file->user_id);
                    if ($categoryId) {
                        $resolved[$categoryName] = $categoryId;
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * Resolve or create a category by name. Called OUTSIDE the main transaction.
     */
    protected function resolveOrCreateCategory(string $categoryName, int $userId): ?int
    {
        $category = Category::where('user_id', $userId)
            ->where('name', $categoryName)
            ->first();

        if ($category) {
            return $category->id;
        }

        $defaultCategories = Category::getDefaultCategories();
        $matchingDefault = collect($defaultCategories)->firstWhere('name', $categoryName);

        if (! $matchingDefault) {
            return null;
        }

        try {
            $this->db->transaction(function () use ($matchingDefault, $userId, &$category) {
                $category = Category::create([
                    'user_id' => $userId,
                    'name' => $matchingDefault['name'],
                    'slug' => Category::generateUniqueSlug($matchingDefault['name'], $userId),
                    'color' => $matchingDefault['color'],
                    'icon' => $matchingDefault['icon'],
                    'is_active' => true,
                ]);

                Log::info('[EntityFactory] Created new category for user', [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'user_id' => $userId,
                ]);
            });

            return $category->id;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $category = Category::where('user_id', $userId)
                ->where('name', $categoryName)
                ->first();

            Log::debug('[EntityFactory] Category was created by concurrent process', [
                'category_name' => $categoryName,
                'user_id' => $userId,
            ]);

            return $category?->id;
        }
    }

    /**
     * Resolve category ID from data.
     */
    protected function resolveCategoryId(array $data, File $file): ?int
    {
        if (! empty($data['category_id'])) {
            return $data['category_id'];
        }

        $categoryName = $data['receipt_category'] ?? null;
        if (empty($categoryName)) {
            return null;
        }

        return $this->resolveOrCreateCategory($categoryName, $file->user_id);
    }
}
