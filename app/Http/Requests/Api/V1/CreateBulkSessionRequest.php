<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class CreateBulkSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file_type' => 'required|in:receipt,document',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => ['integer', new ExistsForUser('collections')],
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => ['integer', new ExistsForUser('tags')],
            'note' => 'nullable|string|max:1000',
            'files' => 'required|array|min:1|max:10000',
            'files.*.filename' => 'required|string|max:255',
            'files.*.path' => 'nullable|string|max:1000',
            'files.*.size' => 'required|integer|min:1|max:104857600', // 100MB
            'files.*.hash' => ['required', 'string', 'regex:/^(sha256:)?[a-f0-9]{64}$/i'],
            'files.*.extension' => 'required|string|in:jpeg,jpg,png,pdf,tiff,tif,csv',
            'files.*.mime_type' => 'required|string|max:100',
            'files.*.file_type' => 'nullable|in:receipt,document',
            'files.*.collection_ids' => 'nullable|array',
            'files.*.collection_ids.*' => ['integer', new ExistsForUser('collections')],
            'files.*.tag_ids' => 'nullable|array',
            'files.*.tag_ids.*' => ['integer', new ExistsForUser('tags')],
            'files.*.note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'file_type.required' => 'A default file type is required.',
            'file_type.in' => 'File type must be receipt or document.',
            'files.required' => 'At least one file must be included in the manifest.',
            'files.max' => 'Maximum 10000 files per session.',
            'files.*.hash.regex' => 'Hash must be a valid SHA-256 hex string, optionally prefixed with sha256:.',
            'files.*.size.max' => 'Maximum file size is 100MB.',
            'files.*.extension.in' => 'Supported formats: jpeg, jpg, png, pdf, tiff, tif, csv.',
        ];
    }
}
