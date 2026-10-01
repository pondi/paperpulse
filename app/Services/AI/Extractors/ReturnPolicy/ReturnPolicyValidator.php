<?php

namespace App\Services\AI\Extractors\ReturnPolicy;

use Illuminate\Support\Facades\Validator;

class ReturnPolicyValidator
{
    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'conditions' => ['required', 'string', 'max:20000'],
            'return_deadline' => ['sometimes', 'date_format:Y-m-d'],
            'exchange_deadline' => ['sometimes', 'date_format:Y-m-d'],
            'restocking_fee' => ['sometimes', 'numeric', 'min:0'],
            'restocking_fee_percentage' => ['sometimes', 'numeric', 'between:0,100'],
            'is_final_sale' => ['sometimes', 'boolean'], 'requires_receipt' => ['sometimes', 'boolean'],
            'requires_original_packaging' => ['sometimes', 'boolean'],
            'confidence_score' => ['sometimes', 'numeric', 'between:0,1'],
        ]);

        return ['valid' => ! $validator->fails(), 'errors' => $validator->errors()->all(), 'warnings' => []];
    }
}
