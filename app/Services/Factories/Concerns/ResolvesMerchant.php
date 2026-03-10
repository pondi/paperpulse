<?php

declare(strict_types=1);

namespace App\Services\Factories\Concerns;

use App\Models\File;

/**
 * Shared merchant resolution logic for entity factories.
 *
 * Expects the using class to have a $merchantEnricher property
 * (ReceiptEnricherService) injected via constructor.
 */
trait ResolvesMerchant
{
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
