<?php

namespace App\Services\AI\Extractors\ReturnPolicy;

class ReturnPolicyDataNormalizer
{
    public function normalize(array $data): array
    {
        $normalized = array_filter($data, static fn (mixed $value): bool => $value !== null);
        if (isset($data['merchant_name'])) {
            $normalized['merchant'] = ['name' => $data['merchant_name']];
        }

        return $normalized;
    }
}
