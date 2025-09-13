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

        // Handle nested receipt structure from fallback response
        if (isset($data['receipt']) && is_array($data['receipt'])) {
            $receiptData = $data['receipt'];

            // Extract items from nested structure
            if (isset($receiptData['items'])) {
                $normalized['items'] = $receiptData['items'];
            }

            // Extract totals from receipt.total
            if (isset($receiptData['total'])) {
                $normalized['totals'] = [
                    'total_amount' => (float) $receiptData['total'],
                    'tax_amount' => self::extractTaxFromVatData($receiptData['vat'] ?? []),
                ];
            }

            // Extract receipt info from nested structure
            $normalized['receipt_info'] = [
                'date' => $receiptData['date'] ?? null,
                'time' => $receiptData['time'] ?? null,
                'receipt_number' => $receiptData['receipt_number'] ?? null,
                'transaction_id' => $receiptData['transaction_id'] ?? null,
            ];

            // Extract payment info
            if (isset($receiptData['payment_method'])) {
                $normalized['payment'] = ['method' => $receiptData['payment_method']];
            }
        }

        // Handle merchant/store mapping (could be at root or nested)
        if (isset($data['merchant'])) {
            $normalized['merchant'] = $data['merchant'];
        } elseif (isset($data['store'])) {
            $normalized['merchant'] = [
                'name' => $data['store']['name'] ?? 'Unknown Merchant',
                'address' => $data['store']['address'] ?? null,
                'vat_number' => $data['store']['organization_number'] ?? null,
                'phone' => $data['store']['phone'] ?? null,
            ];
        } elseif (isset($data['vendor'])) {
            $normalized['merchant'] = $data['vendor'];
        }

        // Fallback for direct structure (non-nested)
        if (! isset($normalized['receipt_info']) && (isset($data['receipt_info']) || isset($data['date']))) {
            if (isset($data['receipt_info'])) {
                $normalized['receipt_info'] = $data['receipt_info'];
            } else {
                $normalized['receipt_info'] = [
                    'date' => $data['date'] ?? null,
                    'time' => $data['time'] ?? null,
                ];
            }
        }

        // Fallback for totals (non-nested)
        if (! isset($normalized['totals'])) {
            if (isset($data['totals'])) {
                $normalized['totals'] = $data['totals'];
            } elseif (isset($data['total'])) {
                $normalized['totals'] = ['total_amount' => (float) $data['total']];
            } elseif (isset($data['total_amount'])) {
                $normalized['totals'] = ['total_amount' => (float) $data['total_amount']];
            }
        }

        // Ensure tax_amount is always present in totals
        if (isset($normalized['totals']) && !isset($normalized['totals']['tax_amount'])) {
            $normalized['totals']['tax_amount'] = 0.0;
        }

        // Fallback for items (non-nested)
        if (! isset($normalized['items'])) {
            if (isset($data['items'])) {
                $normalized['items'] = $data['items'];
            } elseif (isset($data['line_items'])) {
                $normalized['items'] = $data['line_items'];
            }
        }

        // Fallback for payment (non-nested)
        if (! isset($normalized['payment']) && isset($data['payment'])) {
            $normalized['payment'] = $data['payment'];
        } elseif (! isset($normalized['payment']) && isset($data['payment_method'])) {
            $normalized['payment'] = ['method' => $data['payment_method']];
        }

        // Map vendor/brand info
        if (isset($data['vendors']) && is_array($data['vendors'])) {
            $normalized['vendors'] = array_values(array_unique(array_filter($data['vendors'])));
        } elseif (isset($data['brands']) && is_array($data['brands'])) {
            $normalized['vendors'] = array_values(array_unique(array_filter($data['brands'])));
        } elseif (isset($data['product_vendors']) && is_array($data['product_vendors'])) {
            $normalized['vendors'] = array_values(array_unique(array_filter($data['product_vendors'])));
        }

        // Normalize item vendor fields (brand -> vendor)
        if (isset($normalized['items']) && is_array($normalized['items'])) {
            foreach ($normalized['items'] as $idx => $item) {
                if (isset($item['brand']) && !isset($item['vendor'])) {
                    $normalized['items'][$idx]['vendor'] = $item['brand'];
                }
            }
        } elseif (isset($data['items']) && is_array($data['items'])) {
            $normalized['items'] = $data['items'];
            foreach ($normalized['items'] as $idx => $item) {
                if (isset($item['brand']) && !isset($item['vendor'])) {
                    $normalized['items'][$idx]['vendor'] = $item['brand'];
                }
            }
        }

        // Ensure required structure exists with defaults
        if (! isset($normalized['merchant'])) {
            $normalized['merchant'] = ['name' => 'Unknown Merchant'];
        }

        if (! isset($normalized['totals'])) {
            $normalized['totals'] = ['total_amount' => 0, 'tax_amount' => 0.0];
        }

        if (! isset($normalized['receipt_info'])) {
            $normalized['receipt_info'] = ['date' => null];
        }

        // Ensure items is always an array
        if (! isset($normalized['items'])) {
            $normalized['items'] = [];
        }

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
}
