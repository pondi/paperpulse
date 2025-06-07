<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    /**
     * Fields that should never be exposed in API responses
     */
    protected static $hiddenFields = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verified_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot',
        '_token',
        'api_token',
        'session_id',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        
        // Remove any sensitive fields
        foreach (static::$hiddenFields as $field) {
            unset($data[$field]);
        }
        
        // Recursively clean nested data
        return $this->cleanSensitiveData($data);
    }

    /**
     * Recursively remove sensitive data from nested arrays
     */
    protected function cleanSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            // Remove any key that might contain sensitive data
            if ($this->isSensitiveKey($key)) {
                unset($data[$key]);
                continue;
            }
            
            // Recursively clean nested arrays
            if (is_array($value)) {
                $data[$key] = $this->cleanSensitiveData($value);
            }
        }
        
        return $data;
    }

    /**
     * Check if a key name suggests it contains sensitive data
     */
    protected function isSensitiveKey(string $key): bool
    {
        $sensitivePatterns = [
            'password',
            'token',
            'secret',
            'key',
            'salt',
            'hash',
            'pin',
            'ssn',
            'credit_card',
            'cvv',
            'bank',
        ];
        
        $lowercaseKey = strtolower($key);
        
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($lowercaseKey, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
}