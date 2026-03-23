<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePublicCollectionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:255',
            'is_password_protected' => 'boolean',
            'expires_at' => 'nullable|date|after:now',
            'expiration_preset' => 'nullable|in:7d,30d,90d,never,custom',
            'max_views' => 'nullable|integer|min:1',
            'notify_email' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'label.max' => 'Label cannot exceed 255 characters.',
            'expires_at.after' => 'Expiration date must be in the future.',
            'max_views.min' => 'Maximum views must be at least 1.',
            'notify_email.email' => 'Please enter a valid email address.',
        ];
    }

    /**
     * Resolve the expires_at value from either the preset or custom field.
     */
    public function resolvedExpiresAt(): ?string
    {
        $preset = $this->input('expiration_preset');

        if ($preset && $preset !== 'custom' && $preset !== 'never') {
            return match ($preset) {
                '7d' => now()->addDays(7)->toDateTimeString(),
                '30d' => now()->addDays(30)->toDateTimeString(),
                '90d' => now()->addDays(90)->toDateTimeString(),
                default => null,
            };
        }

        if ($preset === 'never') {
            return null;
        }

        return $this->input('expires_at');
    }
}
