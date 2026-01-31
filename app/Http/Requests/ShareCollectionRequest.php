<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShareCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'permission' => 'nullable|in:view,edit',
            'expires_at' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No user found with this email address.',
            'permission.in' => 'Permission must be either "view" or "edit".',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
