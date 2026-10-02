<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_title' => ['sometimes', 'string', 'max:255'],
            'contract_type' => ['sometimes', 'string', 'max:100'],
            'effective_date' => ['sometimes', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date'],
            'contract_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['draft', 'pending', 'active', 'expired', 'terminated', 'renewed'])],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'governing_law' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jurisdiction' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['status.in' => 'Choose a supported contract status.'];
    }
}
