<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\File;
use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        // BelongsToUser scope ensures File::find() returns null for other users' files
        return File::find($this->route('file')) !== null;
    }

    public function rules(): array
    {
        return [
            'note' => 'nullable|string|max:1000',
            'category_id' => ['nullable', 'integer', new ExistsForUser('categories')],
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => ['integer', new ExistsForUser('tags')],
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => ['integer', new ExistsForUser('collections')],
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
