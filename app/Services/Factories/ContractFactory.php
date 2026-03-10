<?php

declare(strict_types=1);

namespace App\Services\Factories;

use App\Models\Contract;
use App\Models\File;

class ContractFactory
{
    public function create(array $data, File $file): Contract
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
}
