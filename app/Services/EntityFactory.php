<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Receipt\ReceiptEnricherService;
use App\Services\Receipts\LineItemsCreator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

class EntityFactory
{
    protected DatabaseManager $db;

    protected ReceiptEnricherService $merchantEnricher;

    public function __construct(DatabaseManager $db, ReceiptEnricherService $merchantEnricher)
    {
        $this->db = $db;
        $this->merchantEnricher = $merchantEnricher;
    }

    /**
     * Create entities from parsed Gemini data.
     *
     * @return array<int, array{type:string, model:mixed}>
     */
    public function createEntitiesFromParsedData(array $parsedData, File $file, string $detectedType = 'document'): array
    {
        $entities = $parsedData['entities'] ?? [];
        $created = [];

        $this->db->transaction(function () use ($entities, $file, &$created) {
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
                // If 'data' is empty but we have typical fields at the top level, use the entity itself as data
                if (empty($data) && ! empty($entity) && count($entity) > 2) { // >2 because it has type, confidence_score, and potential data fields
                    $possibleData = $entity;
                    unset($possibleData['type']);
                    unset($possibleData['confidence_score']);

                    // Check if this looks like valid data for the type
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

                // Inject receipt_id into child entities if we have a parent receipt
                if (isset($context['receipt']) && in_array($type, ['voucher', 'warranty', 'return_policy'])) {
                    $data['receipt_id'] = $context['receipt']->id;
                }

                // Inject invoice_id into child entities if we have a parent invoice
                if (isset($context['invoice']) && in_array($type, ['voucher', 'warranty', 'return_policy', 'invoice_line_items'])) {
                    $data['invoice_id'] = $context['invoice']->id;
                }

                switch ($type) {
                    case 'receipt':
                        $model = $this->createReceipt($data, $file);
                        $context['receipt'] = $model;
                        break;
                    case 'voucher':
                        $model = $this->createVoucher($data, $file);
                        break;
                    case 'warranty':
                        $model = $this->createWarranty($data, $file);
                        break;
                    case 'return_policy':
                        $model = $this->createReturnPolicy($data, $file);
                        break;
                    case 'invoice':
                        $model = $this->createInvoice($data, $file);
                        $context['invoice'] = $model;
                        break;
                    case 'invoice_line_items':
                        $model = null;
                        if (! empty($context['invoice'])) {
                            $items = $data['items'] ?? $data['line_items'] ?? [];
                            $model = $this->createInvoiceLineItems($items, $context['invoice']);
                        }
                        break;
                    case 'contract':
                        $model = $this->createContract($data, $file);
                        break;
                    case 'bank_statement':
                        $model = $this->createBankStatement($data, $file);
                        $context['bank_statement'] = $model;
                        break;
                    case 'bank_transactions':
                        $model = null;
                        if (! empty($context['bank_statement'])) {
                            $transactions = $data['transactions'] ?? [];
                            $model = $this->createBankTransactions($transactions, $context['bank_statement']);
                        }
                        break;
                    case 'document':
                    default:
                        $model = $this->createDocument($data, $file, $type);
                        break;
                }

                if ($model) {
                    if (is_iterable($model)) {
                        foreach ($model as $subModel) {
                            $created[] = ['type' => $type, 'model' => $subModel];

                            // Track in junction table
                            $this->createExtractableEntityRecord($file, $type, $subModel, count($created) === 1, $confidence);
                        }
                    } else {
                        $created[] = ['type' => $type, 'model' => $model];

                        // Track in junction table
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

    protected function createReceipt(array $data, File $file): ?Receipt
    {
        if (empty($data)) {
            return null;
        }

        // Data now follows OpenAI schema structure (unified via responseSchema)
        $totals = $data['totals'] ?? [];
        $receiptInfo = $data['receipt_info'] ?? [];
        $items = $data['items'] ?? [];
        $vendors = $data['vendors'] ?? [];
        $payment = $data['payment'] ?? [];
        $metadata = $data['metadata'] ?? [];

        $merchantId = $data['merchant_id'] ?? $this->resolveMerchantId($data, $file);

        // Map currency from payment or totals, with fallback
        $currency = $payment['currency'] ?? ($totals['currency'] ?? 'NOK');

        // Resolve category ID from category name
        $categoryId = $data['category_id'] ?? $this->resolveCategoryId($data, $file);

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

        // Create line items if present
        if (! empty($items)) {
            LineItemsCreator::create($receipt, $items, $vendors);
        }

        return $receipt;
    }

    protected function createVoucher(array $data, File $file): ?Voucher
    {
        // Require at least ONE identifying field
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

    protected function createWarranty(array $data, File $file): ?Warranty
    {
        // Require at least product name OR manufacturer (one of them must exist)
        $hasProduct = ! empty($data['product_name']) || ! empty($data['manufacturer']);
        $hasWarrantyInfo = $this->hasAny($data, ['warranty_number', 'warranty_end_date', 'warranty_duration', 'coverage_description']);

        if (! $hasProduct && ! $hasWarrantyInfo) {
            return null;
        }

        return Warranty::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'receipt_id' => $data['receipt_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'product_category' => $data['product_category'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'model_number' => $data['model_number'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'warranty_start_date' => $data['warranty_start_date'] ?? null,
            'warranty_end_date' => $data['warranty_end_date'] ?? null,
            'warranty_duration' => $data['warranty_duration'] ?? null,
            'warranty_type' => $data['warranty_type'] ?? null,
            'warranty_provider' => $data['warranty_provider'] ?? null,
            'warranty_number' => $data['warranty_number'] ?? null,
            'coverage_type' => $data['coverage_type'] ?? null,
            'coverage_description' => $data['coverage_description'] ?? null,
            'exclusions' => $data['exclusions'] ?? null,
            'support_phone' => $data['support_phone'] ?? null,
            'support_email' => $data['support_email'] ?? null,
            'support_website' => $data['support_website'] ?? null,
            'warranty_data' => $data['warranty_data'] ?? $data,
        ]);
    }

    protected function createReturnPolicy(array $data, File $file): ?ReturnPolicy
    {
        // Accept if ANY meaningful data is present (structured or unstructured)
        $hasStructuredData = $this->hasAny($data, ['return_deadline', 'exchange_deadline', 'conditions', 'refund_method']);
        $hasUnstructuredData = ! empty($data['description']) || ! empty($data['policy']);

        if (! $hasStructuredData && ! $hasUnstructuredData) {
            return null;
        }

        // Map unstructured text to structured fields where possible
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

    protected function createInvoice(array $data, File $file): Invoice
    {
        return Invoice::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'merchant_id' => $data['merchant_id'] ?? $this->resolveMerchantId($data, $file),
            'category_id' => $data['category_id'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'invoice_type' => $data['invoice_type'] ?? 'invoice',
            'from_name' => $data['from_name'] ?? null,
            'from_address' => $data['from_address'] ?? null,
            'from_vat_number' => $data['from_vat_number'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'from_phone' => $data['from_phone'] ?? null,
            'to_name' => $data['to_name'] ?? null,
            'to_address' => $data['to_address'] ?? null,
            'to_vat_number' => $data['to_vat_number'] ?? null,
            'to_email' => $data['to_email'] ?? null,
            'to_phone' => $data['to_phone'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'subtotal' => $data['subtotal'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'shipping_amount' => $data['shipping_amount'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'amount_paid' => $data['amount_paid'] ?? 0,
            'amount_due' => $data['amount_due'] ?? 0,
            'currency' => $data['currency'] ?? 'NOK',
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => $data['payment_status'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'purchase_order_number' => $data['purchase_order_number'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'invoice_data' => $data['invoice_data'] ?? $data,
        ]);
    }

    protected function createInvoiceLineItems(array $items, Invoice $invoice): array
    {
        $created = [];
        foreach ($items as $index => $item) {
            $created[] = InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'line_number' => $item['line_number'] ?? ($index + 1),
                'description' => $item['description'] ?? null,
                'sku' => $item['sku'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'unit_of_measure' => $item['unit_of_measure'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'discount_percent' => $item['discount_percent'] ?? null,
                'discount_amount' => $item['discount_amount'] ?? null,
                'tax_rate' => $item['tax_rate'] ?? null,
                'tax_amount' => $item['tax_amount'] ?? null,
                'total_amount' => $item['total_amount'] ?? null,
                'category' => $item['category'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return $created;
    }

    protected function createContract(array $data, File $file): Contract
    {
        return Contract::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'contract_number' => $data['contract_number'] ?? null,
            'contract_title' => $data['contract_title'] ?? null,
            'contract_type' => $data['contract_type'] ?? null,
            'parties' => $data['parties'] ?? null,
            'effective_date' => $data['effective_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'signature_date' => $data['signature_date'] ?? null,
            'duration' => $data['duration'] ?? null,
            'renewal_terms' => $data['renewal_terms'] ?? null,
            'termination_conditions' => $data['termination_conditions'] ?? null,
            'contract_value' => $data['contract_value'] ?? null,
            'currency' => $data['currency'] ?? 'NOK',
            'payment_schedule' => $data['payment_schedule'] ?? null,
            'governing_law' => $data['governing_law'] ?? null,
            'jurisdiction' => $data['jurisdiction'] ?? null,
            'status' => $data['status'] ?? null,
            'key_terms' => $data['key_terms'] ?? null,
            'obligations' => $data['obligations'] ?? null,
            'summary' => $data['summary'] ?? null,
            'contract_data' => $data['contract_data'] ?? $data,
        ]);
    }

    protected function createBankStatement(array $data, File $file): ?BankStatement
    {
        if (! $this->hasAny($data, ['account_number', 'iban', 'bank_name', 'statement_date'])) {
            return null;
        }

        return BankStatement::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'bank_name' => $data['bank_name'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'iban' => $data['iban'] ?? null,
            'swift_code' => $data['swift_code'] ?? null,
            'statement_date' => $data['statement_date'] ?? null,
            'statement_period_start' => $data['statement_period_start'] ?? null,
            'statement_period_end' => $data['statement_period_end'] ?? null,
            'opening_balance' => $data['opening_balance'] ?? null,
            'closing_balance' => $data['closing_balance'] ?? null,
            'currency' => $data['currency'] ?? 'NOK',
            'total_credits' => $data['total_credits'] ?? null,
            'total_debits' => $data['total_debits'] ?? null,
            'transaction_count' => $data['transaction_count'] ?? null,
            'statement_data' => $data['statement_data'] ?? $data,
        ]);
    }

    protected function createBankTransactions(array $transactions, BankStatement $statement): array
    {
        $created = [];
        foreach ($transactions as $txn) {
            $created[] = BankTransaction::create([
                'bank_statement_id' => $statement->id,
                'transaction_date' => $txn['transaction_date'] ?? null,
                'posting_date' => $txn['posting_date'] ?? null,
                'description' => $txn['description'] ?? null,
                'reference' => $txn['reference'] ?? null,
                'transaction_type' => $txn['transaction_type'] ?? null,
                'category' => $txn['category'] ?? null,
                'amount' => $txn['amount'] ?? null,
                'balance_after' => $txn['balance_after'] ?? null,
                'currency' => $txn['currency'] ?? $statement->currency ?? 'NOK',
                'counterparty_name' => $txn['counterparty_name'] ?? null,
                'counterparty_account' => $txn['counterparty_account'] ?? null,
            ]);
        }

        return $created;
    }

    protected function createDocument(array $data, File $file, string $type): ?Document
    {
        // Only create a document record when explicit data is provided.
        if (empty($data)) {
            return null;
        }

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $metadataTitle = is_string($metadata['title'] ?? null) ? $metadata['title'] : null;
        $metadataType = is_string($metadata['type'] ?? null) ? $metadata['type'] : null;
        $metadataLanguage = is_string($metadata['language'] ?? null) ? $metadata['language'] : null;
        $creationInfo = is_array($data['creation_info'] ?? null) ? $data['creation_info'] : [];

        $content = $data['content'] ?? null;
        $contentSummary = null;
        $keyPoints = [];
        if (is_array($content)) {
            $contentSummary = is_string($content['summary'] ?? null) ? $content['summary'] : null;
            $keyPoints = is_array($content['key_points'] ?? null)
                ? array_values(array_filter($content['key_points'], 'is_string'))
                : [];
        }

        $summary = $data['summary'] ?? $contentSummary;
        if (! is_string($summary)) {
            $summary = null;
        }

        $contentChunks = [];
        if (is_string($content)) {
            $contentChunks[] = $content;
        } elseif (is_string($summary)) {
            $contentChunks[] = $summary;
        }
        if (! empty($keyPoints)) {
            $contentChunks[] = implode("\n", $keyPoints);
        }
        $contentText = $contentChunks === [] ? null : trim(implode("\n\n", $contentChunks));

        $metadata = array_merge($metadata, array_filter([
            'creation_info' => $creationInfo !== [] ? $creationInfo : null,
            'content' => is_array($content) && $content !== [] ? $content : null,
            'tags' => is_array($data['tags'] ?? null) ? array_values(array_filter($data['tags'], 'is_string')) : null,
            'entities_mentioned' => is_array($data['entities_mentioned'] ?? null) ? $data['entities_mentioned'] : null,
        ], static fn ($value) => $value !== null));

        if ($metadata === []) {
            $metadata = null;
        }

        $categoryId = $data['category_id'] ?? null;
        if (is_string($categoryId) && ctype_digit($categoryId)) {
            $categoryId = (int) $categoryId;
        } elseif (! is_int($categoryId)) {
            $categoryId = null;
        }

        $description = $data['description'] ?? null;
        if (! is_string($description)) {
            $description = null;
        }

        return Document::create([
            'file_id' => $file->id,
            'user_id' => $file->user_id,
            'category_id' => $categoryId,
            'title' => $data['title'] ?? $metadataTitle ?? 'Detected Document',
            'description' => $description,
            'summary' => $summary,
            'content' => $contentText,
            'document_type' => $data['document_type'] ?? $metadataType ?? $type,
            'document_subtype' => $data['document_subtype'] ?? null,
            'document_date' => $data['document_date'] ?? ($creationInfo['creation_date'] ?? null),
            'metadata' => $metadata,
            'extracted_text' => $data['extracted_text'] ?? null,
            'ai_entities' => $data['ai_entities'] ?? $data['entities_mentioned'] ?? null,
            'extracted_entities' => $data['extracted_entities'] ?? null,
            'language' => $data['language'] ?? $metadataLanguage,
            'page_count' => $data['page_count'] ?? 1,
        ]);
    }

    /**
     * Build a merchant payload and resolve/create the merchant, returning the ID.
     */
    protected function resolveMerchantId(array $data, File $file): ?int
    {
        $merchant = $data['merchant'] ?? [];

        // Fallback to loose fields if provided
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

    /**
     * Resolve category ID from category name.
     */
    protected function resolveCategoryId(array $data, File $file): ?int
    {
        $categoryName = $data['receipt_category'] ?? null;

        if (empty($categoryName)) {
            return null;
        }

        // Try to find existing category for this user
        $category = Category::where('user_id', $file->user_id)
            ->where('name', $categoryName)
            ->first();

        // If not found, create a new category from the default categories
        if (! $category) {
            $defaultCategories = Category::getDefaultCategories();
            $matchingDefault = collect($defaultCategories)->firstWhere('name', $categoryName);

            if ($matchingDefault) {
                try {
                    $category = Category::create([
                        'user_id' => $file->user_id,
                        'name' => $matchingDefault['name'],
                        'slug' => Category::generateUniqueSlug($matchingDefault['name'], $file->user_id),
                        'color' => $matchingDefault['color'],
                        'icon' => $matchingDefault['icon'],
                        'is_active' => true,
                    ]);

                    Log::info('[EntityFactory] Created new category for user', [
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'user_id' => $file->user_id,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Race condition: another process created the category, fetch it
                    $category = Category::where('user_id', $file->user_id)
                        ->where('name', $categoryName)
                        ->first();

                    Log::debug('[EntityFactory] Category was created by concurrent process', [
                        'category_name' => $categoryName,
                        'user_id' => $file->user_id,
                    ]);
                }
            }
        }

        return $category?->id;
    }

    /**
     * Determine if payload has any meaningful values for the given keys.
     */
    protected function hasAny(array $data, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && ! empty($data[$key])) {
                return true;
            }
        }

        return false;
    }
}
