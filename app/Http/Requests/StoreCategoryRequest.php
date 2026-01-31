<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A category name is required.',
            'name.max' => 'Category name cannot exceed 255 characters.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733).',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
