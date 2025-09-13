<?php

namespace App\Services\AI\OpenAI;

use App\Services\AI\Shared\AIFallbackHandler;

/**
 * Creates relaxed OpenAI payloads for fallback attempts.
 */
class FallbackPayloadFactory
{
    /**
     * Merge default fallback payload with overrides.
     *
     * @param array $messages
     * @param string $model
     * @param array $params
     * @return array
     */
    public static function make(array $messages, string $model, array $params = []): array
    {
        $payload = AIFallbackHandler::createOpenAIFallbackPayload($messages, $model);
        return array_merge($payload, $params);
    }
}
