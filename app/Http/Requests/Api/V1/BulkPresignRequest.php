<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkPresignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file_uuids' => 'required|array|min:1|max:50',
            'file_uuids.*' => 'required|uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'file_uuids.required' => 'At least one file UUID is required.',
            'file_uuids.max' => 'Maximum 50 files per presign request.',
            'file_uuids.*.uuid' => 'Each file UUID must be a valid UUID.',
        ];
    }
}
