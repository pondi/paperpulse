<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\File;
use App\Models\Warranty;
use App\Services\Factories\Concerns\ChecksDataPresence;

class WarrantyFactory extends BaseEntityFactory
{
    use ChecksDataPresence;

    protected function modelClass(): string
    {
        return Warranty::class;
    }

    protected function fields(): array
    {
        return [
            'receipt_id',
            'invoice_id',
            'product_name',
            'product_category',
            'manufacturer',
            'model_number',
            'serial_number',
            'purchase_date',
            'warranty_start_date',
            'warranty_end_date',
            'warranty_duration',
            'warranty_type',
            'warranty_provider',
            'warranty_number',
            'coverage_type',
            'coverage_description',
            'exclusions',
            'support_phone',
            'support_email',
            'support_website',
        ];
    }

    protected function dateFields(): array
    {
        return ['purchase_date', 'warranty_start_date', 'warranty_end_date'];
    }

    protected function rawDataField(): ?string
    {
        return 'warranty_data';
    }

    protected function prepareData(array $data, File $file): array
    {
        return array_merge($data, array_filter([
            'product_name' => $data['product']['name'] ?? null,
            'model_number' => $data['product']['model'] ?? null,
            'serial_number' => $data['product']['serial_number'] ?? null,
            'warranty_provider' => $data['provider']['name'] ?? null,
            'purchase_date' => $data['dates']['purchase_date'] ?? null,
            'warranty_start_date' => $data['dates']['warranty_start_date'] ?? null,
            'warranty_end_date' => $data['dates']['warranty_end_date'] ?? null,
            'coverage_type' => $data['coverage']['type'] ?? null,
            'coverage_description' => $data['coverage']['details'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    protected function shouldCreate(array $data): bool
    {
        $hasProduct = ! empty($data['product_name']) || ! empty($data['manufacturer']);
        $hasWarrantyInfo = $this->hasAny($data, ['warranty_number', 'warranty_end_date', 'warranty_duration', 'coverage_description']);

        return $hasProduct || $hasWarrantyInfo;
    }
}
