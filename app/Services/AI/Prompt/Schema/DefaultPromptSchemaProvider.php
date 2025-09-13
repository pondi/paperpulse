<?php

namespace App\Services\AI\Prompt\Schema;

class DefaultPromptSchemaProvider
{
    public static function schema(string $templateName): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string'],
            ],
            'required' => ['result'],
            'additionalProperties' => false,
        ];
    }
}

