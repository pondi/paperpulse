<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'note' => 'nullable|string|max:1000',
            'category_id' => 'nullable|integer|exists:categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'integer|exists:collections,id',
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Note must not exceed 1000 characters.',
            'category_id.exists' => 'The selected category does not exist.',
            'tag_ids.*.exists' => 'One or more selected tags do not exist.',
            'collection_ids.*.exists' => 'One or more selected collections do not exist.',
        ];
    }
}
