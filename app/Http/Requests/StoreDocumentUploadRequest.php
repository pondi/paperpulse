<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['file_type' => $this->input('file_type') ?? 'document']);
    }

    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file',
            'file_type' => 'required|in:receipt,document',
            'note' => 'nullable|string|max:1000',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => ['integer', new ExistsForUser('collections')],
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => ['integer', new ExistsForUser('tags')],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Select at least one file to upload.',
            'files.array' => 'Files must be submitted as a list.',
            'files.*.file' => 'Each upload must be a valid file.',
            'file_type.in' => 'The supplied file type is unsupported.',
            'note.max' => 'The note cannot exceed 1000 characters.',
        ];
    }
}
