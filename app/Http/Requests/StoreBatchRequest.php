<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.file_id' => ['required', 'integer', 'min:1'],
            'items.*.source' => ['prohibited'],
            'items.*.type' => ['sometimes', 'in:receipt,document'],
            'items.*.options' => ['sometimes', 'array'],
            'type' => ['required', 'in:receipt,document'],
            'options' => ['sometimes', 'array'],
            'options.quality' => ['sometimes', 'in:basic,standard,high,premium'],
            'options.budget' => ['sometimes', 'in:economy,standard,premium,unlimited'],
            'options.batch_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.file_id.required' => 'Select an owned file for each batch item.',
            'items.*.source.prohibited' => 'Batch items must use file IDs, not paths or URLs.',
            'type.in' => 'Choose receipt or document processing.',
            'items.*.type.in' => 'Choose receipt or document processing.',
        ];
    }
}
