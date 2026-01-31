<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Collection name cannot exceed 100 characters.',
            'description.max' => 'Description cannot exceed 500 characters.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733).',
        ];
    }
}
