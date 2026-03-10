<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\Contract;

class ContractFactory extends BaseEntityFactory
{
    protected function modelClass(): string
    {
        return Contract::class;
    }

    protected function fields(): array
    {
        return [
            'contract_number',
            'contract_title',
            'contract_type',
            'parties',
            'effective_date',
            'expiry_date',
            'signature_date',
            'duration',
            'renewal_terms',
            'termination_conditions',
            'contract_value',
            'currency',
            'payment_schedule',
            'governing_law',
            'jurisdiction',
            'status',
            'key_terms',
            'obligations',
            'summary',
        ];
    }

    protected function dateFields(): array
    {
        return ['effective_date', 'expiry_date', 'signature_date'];
    }

    protected function defaults(): array
    {
        return ['currency' => 'NOK'];
    }

    protected function rawDataField(): ?string
    {
        return 'contract_data';
    }
}
