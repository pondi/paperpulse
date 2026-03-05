<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Warranty;

class WarrantyFactory
{
    public function create(array $data, File $file): ?Warranty
    {
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
