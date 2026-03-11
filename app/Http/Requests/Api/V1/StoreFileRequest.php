<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $fileType = $this->input('file_type', 'receipt');

        $allowedFormats = $fileType === 'document'
            ? implode(',', config('processing.documents.supported_formats.documents'))
            : implode(',', config('processing.documents.supported_formats.receipts'));

        return [
            'file' => "required|file|mimes:{$allowedFormats}|max:102400", // 100MB
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
        $fileType = $this->input('file_type', 'receipt');

        $formats = $fileType === 'document'
            ? implode(', ', config('processing.documents.supported_formats.documents'))
            : implode(', ', config('processing.documents.supported_formats.receipts'));

        return [
            'file.required' => 'A file is required.',
            'file.file' => 'Upload must be a valid file.',
            'file.mimes' => "Supported formats for {$fileType}: {$formats}.",
            'file.max' => 'Maximum file size is 100MB.',
            'file_type.in' => 'File type must be receipt or document.',
        ];
    }
}
