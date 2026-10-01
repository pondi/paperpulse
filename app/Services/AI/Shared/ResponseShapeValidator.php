<?php

namespace App\Services\AI\Shared;

use App\Exceptions\AIResponseException;

class ResponseShapeValidator
{
    public static function validate(mixed $value, array $schema): void
    {
        $type = $schema['type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        $matches = false;
        foreach ($types as $candidate) {
            $matches = $matches || match (strtolower((string) $candidate)) {
                'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
                'array' => is_array($value) && array_is_list($value),
                'string' => is_string($value),
                'number' => is_int($value) || is_float($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                '' => true,
                default => false,
            };
        }
        if (! $matches) {
            throw new AIResponseException('AI response field has an invalid type');
        }
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            throw new AIResponseException('AI response field has an unsupported value');
        }
        if (is_array($value)) {
            foreach ($schema['required'] ?? [] as $field) {
                if (! array_key_exists($field, $value)) {
                    throw new AIResponseException('AI response is missing a mandatory field');
                }
            }
            foreach ($schema['properties'] ?? [] as $field => $definition) {
                if (array_key_exists($field, $value)) {
                    self::validate($value[$field], $definition);
                }
            }
            if (isset($schema['items'])) {
                foreach ($value as $item) {
                    self::validate($item, $schema['items']);
                }
            }
        }
    }
}
