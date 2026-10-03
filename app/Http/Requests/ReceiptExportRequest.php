<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from_date'), 'after_or_equal:from_date')],
            'merchant_id' => ['nullable', 'integer', new ExistsForUser('merchants')],
            'category' => ['nullable', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['receipt_date', 'total_amount'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    public function messages(): array
    {
        return ['*.date_format' => 'Use a date in YYYY-MM-DD format.', 'to_date.after_or_equal' => 'The end date must follow the start date.',
            'sort.in' => 'Choose a supported receipt sort field.', 'sort_direction.in' => 'Choose ascending or descending order.',
            'category.max' => 'The category must be at most 100 characters.'];
    }
}
