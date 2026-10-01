<?php

namespace App\Http\Requests\Files;

use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectFileTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = $this->route('file');

        return $file instanceof File && $file->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return ['file_type' => ['required', 'string', Rule::in(EntityExtractorFactory::getSupportedTypes())]];
    }

    public function messages(): array
    {
        return ['file_type.required' => 'Choose the document type before retrying extraction.', 'file_type.in' => 'Choose a supported document type.'];
    }
}
