<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationBackfillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['extract_missing' => 'required|boolean', 'max_calls' => 'required|integer|min:1|max:100',
            'max_tokens' => 'required|integer|min:10000|max:1000000'];
    }

    public function messages(): array
    {
        return ['max_calls.max' => 'Limit each backfill to at most 100 provider calls.', 'max_tokens.max' => 'Limit each backfill to at most one million reserved tokens.'];
    }
}
