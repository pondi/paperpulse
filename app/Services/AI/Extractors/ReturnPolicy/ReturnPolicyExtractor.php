<?php

namespace App\Services\AI\Extractors\ReturnPolicy;

use App\Exceptions\AIResponseException;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorContract;
use App\Services\AI\Providers\GeminiProvider;

class ReturnPolicyExtractor implements EntityExtractorContract
{
    public function __construct(protected GeminiProvider $provider, protected ReturnPolicyValidator $validator, protected ReturnPolicyDataNormalizer $normalizer) {}

    public function extract(string $fileUri, File $file, array $context = []): array
    {
        $response = $this->provider->analyzeFileByUri($fileUri, $this->getSchema(), $this->getPrompt(), [], $context['mime_type'] ?? $file->fileType ?? 'application/pdf');
        $data = $response['data'];
        $validation = $this->validator->validate($data);
        if (! $validation['valid']) {
            throw new AIResponseException('Return policy validation failed');
        }

        return ['type' => 'return_policy', 'confidence_score' => $data['confidence_score'] ?? 0.85, 'data' => $this->normalizer->normalize($data)];
    }

    public function getSchema(): array
    {
        return ReturnPolicySchema::get();
    }

    public function getPrompt(): string
    {
        return ReturnPolicySchema::getPrompt();
    }
}
