<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:200'],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'name', 'status'])],
            'status' => ['nullable', Rule::in(['pending', 'processing', 'failed', 'completed', 'needs_review'])],
            'file_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([50, 100, 200, 999999])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.max' => 'Search filenames using at most 200 characters.',
            'sort.in' => 'Choose an available activity sort order.',
            'status.in' => 'Choose an available processing status.',
            'per_page.in' => 'Choose an available page size.',
        ];
    }
}
