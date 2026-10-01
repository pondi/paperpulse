<?php

namespace App\Services\AI\Extractors\ReturnPolicy;

class ReturnPolicySchema
{
    public static function get(): array
    {
        return ['name' => 'return_policy_extraction', 'responseSchema' => [
            'type' => 'object', 'properties' => [
                'merchant_name' => ['type' => 'string'], 'conditions' => ['type' => 'string'],
                'return_deadline' => ['type' => 'string'], 'exchange_deadline' => ['type' => 'string'],
                'refund_method' => ['type' => 'string'], 'restocking_fee' => ['type' => 'number'],
                'restocking_fee_percentage' => ['type' => 'number'], 'is_final_sale' => ['type' => 'boolean'],
                'requires_receipt' => ['type' => 'boolean'], 'requires_original_packaging' => ['type' => 'boolean'],
                'confidence_score' => ['type' => 'number'],
            ], 'required' => ['conditions'],
        ]];
    }

    public static function getPrompt(): string
    {
        return 'Extract explicitly stated return/exchange policy terms. Preserve false and zero values. Omit absent fields. Dates must be YYYY-MM-DD. Do not infer deadlines. Return conditions, refund method, fees, receipt/packaging requirements and merchant name when stated.';
    }
}
