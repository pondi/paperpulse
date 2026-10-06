<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ExistsForUser;
use App\Services\File\FileValidationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['file_type' => $this->input('file_type') ?? 'document']);
    }

    public function rules(): array
    {
        $fileType = $this->input('file_type', 'document');

        return [
            'file' => ['bail', 'required', 'file', function (string $attribute, UploadedFile $value, Closure $fail) use ($fileType): void {
                foreach (app(FileValidationService::class)->validateUploadedFile($value, $fileType)['errors'] as $error) {
                    $fail($error);
                }
            }],
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
            'file.required' => 'A file is required.',
            'file.file' => 'Upload must be a valid file.',
            'file_type.in' => 'File type must be receipt or document.',
        ];
    }
}
