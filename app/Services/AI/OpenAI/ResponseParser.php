<?php

namespace App\Services\AI\OpenAI;

class ResponseParser
{
    public static function jsonContent($response): array
    {
        $content = $response->choices[0]->message->content ?? '{}';
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }
}

