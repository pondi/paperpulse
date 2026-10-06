<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\ExistsForUser;
use App\Services\Files\FileUploadConfigService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateBulkSessionRequest extends FormRequest
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
        $allFormats = array_unique(array_merge(
            array_keys(app(FileUploadConfigService::class)->getCapabilities('receipt')),
            array_keys(app(FileUploadConfigService::class)->getCapabilities('document')),
        ));

        $extensionRule = 'required|string|in:'.implode(',', $allFormats);

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
            'files.*.size' => 'required|integer|min:1',
            'files.*.hash' => ['required', 'string', 'regex:/^(sha256:)?[a-f0-9]{64}$/i'],
            'files.*.extension' => $extensionRule,
            'files.*.mime_type' => 'required|string|max:100',
            'files.*.file_type' => 'nullable|in:receipt,document',
            'files.*.collection_ids' => 'nullable|array',
            'files.*.collection_ids.*' => ['integer', new ExistsForUser('collections')],
            'files.*.tag_ids' => 'nullable|array',
            'files.*.tag_ids.*' => ['integer', new ExistsForUser('tags')],
            'files.*.note' => 'nullable|string|max:1000',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $config = app(FileUploadConfigService::class);
            foreach ($this->input('files', []) as $index => $file) {
                $type = $file['file_type'] ?? $this->input('file_type');
                $capability = $config->getCapabilities($type)[$file['extension']] ?? null;
                if ($capability === null || $file['size'] > $capability['maxBytes']) {
                    $validator->errors()->add("files.{$index}.size", 'File exceeds the supported format or processing size limit.');
                }
            }
        }];
    }

    public function messages(): array
    {
        $allFormats = array_unique(array_merge(
            array_keys(app(FileUploadConfigService::class)->getCapabilities('receipt')),
            array_keys(app(FileUploadConfigService::class)->getCapabilities('document')),
        ));

        return [
            'file_type.required' => 'A default file type is required.',
            'file_type.in' => 'File type must be receipt or document.',
            'files.required' => 'At least one file must be included in the manifest.',
            'files.max' => 'Maximum 10000 files per session.',
            'files.*.hash.regex' => 'Hash must be a valid SHA-256 hex string, optionally prefixed with sha256:.',
            'files.*.extension.in' => 'Supported formats: '.implode(', ', $allFormats).'.',
        ];
    }
}
