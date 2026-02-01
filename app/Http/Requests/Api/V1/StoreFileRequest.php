<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf,tiff,tif|max:102400', // 100MB
            'file_type' => 'required|in:receipt,document',
            'note' => 'nullable|string|max:1000',
            'collection_ids' => 'nullable|array',
            'collection_ids.*' => 'integer|exists:collections,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.file' => 'Upload must be a valid file.',
            'file.mimes' => 'Supported formats: jpeg, jpg, png, pdf, tiff, tif.',
            'file.max' => 'Maximum file size is 100MB.',
            'file_type.in' => 'File type must be receipt or document.',
        ];
    }
}
