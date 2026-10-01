<?php

namespace App\Services\AI\OpenAI;

use App\Exceptions\AIResponseException;
use JsonException;

class ResponseParser
{
    public static function jsonContent(mixed $response): array
    {
        $choice = $response->choices[0] ?? null;
        if (($choice?->finishReason ?? null) === 'length') {
            throw new AIResponseException('AI output was truncated', true);
        }
        if (($choice?->finishReason ?? null) !== 'stop' || ! empty($choice?->message?->refusal)) {
            throw new AIResponseException('AI output was refused or incomplete');
        }
        $content = $choice?->message?->content;
        if (! is_string($content) || trim($content) === '') {
            throw new AIResponseException('AI output was empty');
        }
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AIResponseException('AI output was malformed JSON');
        }
        if (! is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            throw new AIResponseException('AI output must contain a nonempty JSON object');
        }

        return $decoded;
    }
}
