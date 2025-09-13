<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptValidatorContract;

class ParsedDataValidator
{
    public static function validateAndSanitize(array $analysisData, int $fileId, ReceiptValidatorContract $validator): array
    {
        $validation = $validator->validateParsedData($analysisData, $fileId);
        if (!$validation['valid']) {
            throw new \Exception('Receipt data validation failed: '.implode(', ', $validation['errors']));
        }

        if (!$validator->hasEssentialData($analysisData)) {
            throw new \Exception('AI analysis failed to extract essential merchant information');
        }

        $data = $validator->sanitizeData($analysisData);

        return [$data, $validation['warnings'] ?? []];
    }
}

