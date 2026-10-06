<?php

namespace App\Services\AI\Extractors\ReturnPolicy;

class ReturnPolicyDataNormalizer
{
    public function normalize(array $data): array
    {
        $data = $this->normalizeRelativeDeadlines($data);
        $normalized = array_filter($data, static fn (mixed $value): bool => $value !== null);
        if (isset($data['merchant_name'])) {
            $normalized['merchant'] = ['name' => $data['merchant_name']];
        }

        return $normalized;
    }

    public function normalizeRelativeDeadlines(array $data): array
    {
        if (! is_string($data['conditions'] ?? null) || trim($data['conditions']) === '') {
            return $data;
        }

        foreach (['return_deadline' => 'Return period', 'exchange_deadline' => 'Exchange period'] as $field => $label) {
            $deadline = $data[$field] ?? null;
            if (! is_string($deadline) || ! preg_match('/\A\d+\s+(?:(?:business|working|calendar)\s+)?(?:day|week|month|year)s?\z/i', trim($deadline))) {
                continue;
            }

            $data['conditions'] = rtrim($data['conditions']).' '.$label.': '.trim($deadline).'.';
            unset($data[$field]);
        }

        return $data;
    }
}
