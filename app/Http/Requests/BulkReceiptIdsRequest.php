<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkReceiptIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'receipt_ids' => 'required|array',
            'receipt_ids.*' => 'integer|exists:receipts,id',
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_ids.required' => 'At least one receipt must be selected.',
            'receipt_ids.array' => 'Receipt IDs must be provided as an array.',
            'receipt_ids.*.exists' => 'One or more selected receipts do not exist.',
        ];
    }
}
