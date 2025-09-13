<?php

namespace App\Services\AI\OpenAI;

use App\Services\AI\Shared\AIFallbackHandler;

class FallbackPayloadFactory
{
    public static function make(array $messages, string $model, array $params = []): array
    {
        $payload = AIFallbackHandler::createOpenAIFallbackPayload($messages, $model);
        return array_merge($payload, $params);
    }
}

