<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

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
        $body = $this->renderString($this->body, $variables, true);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Only explicitly typed, generated summary/expiry fragments may contain HTML.
     * Substituted URL attributes must resolve to absolute HTTP(S) URLs.
     */
    protected function renderString(string $template, array $variables = [], bool $html = false): string
    {
        $replacements = [];
        $plainReplacements = [];

        foreach ($variables as $key => $value) {
            $text = (string) $value;
            $plainReplacements["{{ $key }}"] = $text;
            $trustedHtml = $value instanceof HtmlString && in_array($key, ['categories_summary', 'merchants_summary', 'expires_info'], true);
            $replacements["{{ $key }}"] = $html && ! $trustedHtml ? e($text) : $text;
        }

        if ($html) {
            preg_match_all('/\b(?:href|src)\s*=\s*([\'"])(.*?)\1/is', $template, $attributes, PREG_SET_ORDER);
            foreach ($attributes as $attribute) {
                if (! str_contains($attribute[2], '{{ ')) {
                    continue;
                }
                $url = html_entity_decode(strtr($attribute[2], $plainReplacements), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                    throw new InvalidArgumentException('Invalid email template URL');
                }
            }
        }

        $rendered = strtr($template, $replacements);

        return $html ? $rendered : preg_replace('/[\r\n]+/', ' ', strip_tags($rendered));
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
            if (! array_key_exists($variable, $variables)) {
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
    protected static function boot(): void
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
