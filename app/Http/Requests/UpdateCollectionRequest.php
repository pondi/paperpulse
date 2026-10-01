<?php

namespace App\Http\Requests;

use App\Models\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->whereNull('deleted_at')->where('is_archived', false))],
            'is_archived' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => ['nullable', 'string', Rule::in(Collection::ICONS)],
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
