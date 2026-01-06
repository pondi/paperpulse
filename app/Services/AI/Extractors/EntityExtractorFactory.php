<?php

namespace App\Services\AI\Extractors;

use App\Services\AI\Extractors\BankStatement\BankStatementExtractor;
use App\Services\AI\Extractors\Contract\ContractExtractor;
use App\Services\AI\Extractors\Document\DocumentExtractor;
use App\Services\AI\Extractors\Invoice\InvoiceExtractor;
use App\Services\AI\Extractors\Receipt\ReceiptExtractor;
use App\Services\AI\Extractors\Voucher\VoucherExtractor;
use App\Services\AI\Extractors\Warranty\WarrantyExtractor;
use InvalidArgumentException;

/**
 * Factory for creating type-specific entity extractors.
 *
 * Follows the pattern established by OCRServiceFactory.
 */
class EntityExtractorFactory
{
    /**
     * Create an extractor for the given entity type.
     *
     * @param  string  $entityType  Entity type (receipt, invoice, voucher, etc.)
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $entityType): EntityExtractorContract
    {
        return match ($entityType) {
            'receipt' => app(ReceiptExtractor::class),
            'invoice' => app(InvoiceExtractor::class),
            'voucher' => app(VoucherExtractor::class),
            'warranty' => app(WarrantyExtractor::class),
            'contract' => app(ContractExtractor::class),
            'bank_statement' => app(BankStatementExtractor::class),
            'document' => app(DocumentExtractor::class),
            default => throw new InvalidArgumentException("No extractor available for entity type: {$entityType}. Available types: ".implode(', ', self::getSupportedTypes()))
        };
    }

    /**
     * Check if an extractor exists for the given type.
     */
    public static function hasExtractor(string $entityType): bool
    {
        return in_array($entityType, self::getSupportedTypes(), true);
    }

    /**
     * Get list of supported entity types.
     */
    public static function getSupportedTypes(): array
    {
        return [
            'receipt',
            'invoice',
            'voucher',
            'warranty',
            'contract',
            'bank_statement',
            'document',
        ];
    }
}
