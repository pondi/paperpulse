<?php

namespace App\Services\AI\OpenAI;

/**
 * Helpers to parse OpenAI chat responses into arrays.
 */
class ResponseParser
{
    /**
     * Decode the first choice message content as JSON.
     *
     * @param  mixed  $response  OpenAI response object
     */
    public static function jsonContent($response): array
    {
        $content = $response->choices[0]->message->content ?? '{}';
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
