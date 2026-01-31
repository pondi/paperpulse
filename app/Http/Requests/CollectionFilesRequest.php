<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectionFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'integer|exists:files,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file_ids.required' => 'At least one file must be selected.',
            'file_ids.array' => 'File IDs must be provided as an array.',
            'file_ids.min' => 'At least one file must be selected.',
            'file_ids.*.exists' => 'One or more selected files do not exist.',
        ];
    }
}
