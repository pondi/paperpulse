<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
        'variables',
        'description',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get template by key with caching
     */
    public static function getByKey(string $key): ?self
    {
        return Cache::remember("email_template_{$key}", 3600, function () use ($key) {
            return self::where('key', $key)->where('is_active', true)->first();
        });
    }

    /**
     * Render the template with variables
     */
    public function render(array $variables = []): array
    {
        $subject = $this->renderString($this->subject, $variables);
        $body = $this->renderString($this->body, $variables);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Render a string with variables
     */
    protected function renderString(string $template, array $variables = []): string
    {
        $rendered = $template;
        
        foreach ($variables as $key => $value) {
            $placeholder = "{{ $key }}";
            $rendered = str_replace($placeholder, (string) $value, $rendered);
        }

        return $rendered;
    }

    /**
     * Get required variables for this template
     */
    public function getRequiredVariables(): array
    {
        return $this->variables ?? [];
    }

    /**
     * Validate that all required variables are provided
     */
    public function validateVariables(array $variables): array
    {
        $required = $this->getRequiredVariables();
        $missing = [];

        foreach ($required as $variable) {
            if (!array_key_exists($variable, $variables)) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * Clear the cache for this template
     */
    public function clearCache(): void
    {
        Cache::forget("email_template_{$this->key}");
    }

    /**
     * Boot method to clear cache on save/delete
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($template) {
            $template->clearCache();
        });

        static::deleted(function ($template) {
            $template->clearCache();
        });
    }
}