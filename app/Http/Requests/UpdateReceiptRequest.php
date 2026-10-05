<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'category_id' => ['sometimes', 'nullable', 'integer', new ExistsForUser('categories')],
            'receipt_category' => ['nullable', 'string', 'max:255'],
            'receipt_description' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'merchant_id' => ['nullable', new ExistsForUser('merchants')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', new ExistsForUser('tags')],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_date.required' => 'A receipt date is required.',
            'total_amount.numeric' => 'The total must be a valid amount.',
            'currency.size' => 'Use a three-letter currency code.',
        ];
    }
}
