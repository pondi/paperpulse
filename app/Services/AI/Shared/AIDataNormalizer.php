<?php

namespace App\Services\AI\Shared;

/**
 * Normalizes heterogeneous AI outputs into expected internal structures.
 */
class AIDataNormalizer
{
    /**
     * Normalize receipt data structure from various AI response formats
     * to match validation expectations.
     */
    public static function normalizeReceiptData(array $data): array
    {
        $normalized = [];

        self::normalizeNestedReceipt($data, $normalized);
        self::normalizeMerchantData($data, $normalized);
        self::normalizeReceiptInfo($data, $normalized);
        self::normalizeTotals($data, $normalized);
        self::normalizeItems($data, $normalized);
        self::normalizePayment($data, $normalized);
        self::normalizeVendorLists($data, $normalized);
        self::normalizeItemVendors($data, $normalized);
        self::ensureRequiredDefaults($normalized);

        return $normalized;
    }

    /**
     * Extract total tax amount from Norwegian VAT data structure.
     */
    private static function extractTaxFromVatData(array $vatData): float
    {
        $totalTax = 0.0;

        foreach ($vatData as $vatEntry) {
            if (isset($vatEntry['vat_amount']) && is_numeric($vatEntry['vat_amount'])) {
                $totalTax += (float) $vatEntry['vat_amount'];
            }
        }

        return $totalTax;
    }

    /**
     * Normalize document data structure.
     */
    public static function normalizeDocumentData(array $data): array
    {
        // For now, document data doesn't need much normalization
        // But this can be extended if different providers return different structures
        return $data;
    }

    private static function normalizeNestedReceipt(array $data, array &$normalized): void
    {
        if (! isset($data['receipt']) || ! is_array($data['receipt'])) {
            return;
        }

        $receiptData = $data['receipt'];

        if (isset($receiptData['items'])) {
            $normalized['items'] = $receiptData['items'];
        }

        if (isset($receiptData['total'])) {
            $normalized['totals'] = [
                'total_amount' => (float) $receiptData['total'],
                'tax_amount' => self::extractTaxFromVatData($receiptData['vat'] ?? []),
            ];
        }

        $normalized['receipt_info'] = [
            'date' => $receiptData['date'] ?? null,
            'time' => $receiptData['time'] ?? null,
            'receipt_number' => $receiptData['receipt_number'] ?? null,
            'transaction_id' => $receiptData['transaction_id'] ?? null,
        ];

        if (isset($receiptData['payment_method'])) {
            $normalized['payment'] = ['method' => $receiptData['payment_method']];
        }
    }

    private static function normalizeMerchantData(array $data, array &$normalized): void
    {
        if (isset($data['merchant'])) {
            $normalized['merchant'] = $data['merchant'];

            return;
        }

        if (isset($data['store'])) {
            $normalized['merchant'] = [
                'name' => $data['store']['name'] ?? 'Unknown Merchant',
                'address' => $data['store']['address'] ?? null,
                'vat_number' => $data['store']['organization_number'] ?? null,
                'phone' => $data['store']['phone'] ?? null,
            ];

            return;
        }

        if (isset($data['vendor'])) {
            $normalized['merchant'] = $data['vendor'];
        }
    }

    private static function normalizeReceiptInfo(array $data, array &$normalized): void
    {
        if (isset($normalized['receipt_info'])) {
            return;
        }

        if (isset($data['receipt_info'])) {
            $normalized['receipt_info'] = $data['receipt_info'];

            return;
        }

        if (isset($data['date']) || isset($data['time'])) {
            $normalized['receipt_info'] = [
                'date' => $data['date'] ?? null,
                'time' => $data['time'] ?? null,
            ];
        }
    }

    private static function normalizeTotals(array $data, array &$normalized): void
    {
        if (! isset($normalized['totals'])) {
            if (isset($data['totals'])) {
                $normalized['totals'] = $data['totals'];
            } elseif (isset($data['total'])) {
                $normalized['totals'] = ['total_amount' => (float) $data['total']];
            } elseif (isset($data['total_amount'])) {
                $normalized['totals'] = ['total_amount' => (float) $data['total_amount']];
            }
        }

        if (isset($normalized['totals']) && ! isset($normalized['totals']['tax_amount'])) {
            $normalized['totals']['tax_amount'] = 0.0;
        }
    }

    private static function normalizeItems(array $data, array &$normalized): void
    {
        if (isset($normalized['items'])) {
            return;
        }

        if (isset($data['items'])) {
            $normalized['items'] = $data['items'];

            return;
        }

        if (isset($data['line_items'])) {
            $normalized['items'] = $data['line_items'];
        }
    }

    private static function normalizePayment(array $data, array &$normalized): void
    {
        if (isset($normalized['payment'])) {
            return;
        }

        if (isset($data['payment'])) {
            $normalized['payment'] = $data['payment'];

            return;
        }

        if (isset($data['payment_method'])) {
            $normalized['payment'] = ['method' => $data['payment_method']];
        }
    }

    private static function normalizeVendorLists(array $data, array &$normalized): void
    {
        if (isset($data['vendors']) && is_array($data['vendors'])) {
            $normalized['vendors'] = array_values(array_unique(array_filter($data['vendors'])));

            return;
        }

        if (isset($data['brands']) && is_array($data['brands'])) {
            $normalized['vendors'] = array_values(array_unique(array_filter($data['brands'])));

            return;
        }

        if (isset($data['product_vendors']) && is_array($data['product_vendors'])) {
            $normalized['vendors'] = array_values(array_unique(array_filter($data['product_vendors'])));
        }
    }

    private static function normalizeItemVendors(array $data, array &$normalized): void
    {
        if (isset($normalized['items']) && is_array($normalized['items'])) {
            foreach ($normalized['items'] as $idx => $item) {
                if (isset($item['brand']) && ! isset($item['vendor'])) {
                    $normalized['items'][$idx]['vendor'] = $item['brand'];
                }
            }

            return;
        }

        if (! isset($data['items']) || ! is_array($data['items'])) {
            return;
        }

        $normalized['items'] = $data['items'];
        foreach ($normalized['items'] as $idx => $item) {
            if (isset($item['brand']) && ! isset($item['vendor'])) {
                $normalized['items'][$idx]['vendor'] = $item['brand'];
            }
        }
    }

    private static function ensureRequiredDefaults(array &$normalized): void
    {
        if (! isset($normalized['merchant'])) {
            $normalized['merchant'] = ['name' => 'Unknown Merchant'];
        }

        if (! isset($normalized['totals'])) {
            $normalized['totals'] = ['total_amount' => 0, 'tax_amount' => 0.0];
        }

        if (! isset($normalized['receipt_info'])) {
            $normalized['receipt_info'] = ['date' => null];
        }

        if (! isset($normalized['items'])) {
            $normalized['items'] = [];
        }
    }
}
