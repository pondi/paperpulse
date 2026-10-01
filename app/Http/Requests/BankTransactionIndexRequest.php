<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankTransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,200'],
            'sort' => ['sometimes', Rule::in(['transaction_date', 'amount', 'balance_after', 'description', 'category_group'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'type' => ['nullable', Rule::in(['credit', 'debit', 'fee', 'transfer', 'interest', 'other'])],
            'category_group' => ['nullable', Rule::enum(TransactionCategory::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.between' => 'Choose between 1 and 200 transactions per page.',
            'sort.in' => 'Choose a supported transaction sort column.',
            'sort_direction.in' => 'Choose ascending or descending order.',
            'date_to.after_or_equal' => 'The end date must follow the start date.',
            'category_group.enum' => 'Choose a supported transaction category.',
        ];
    }
}
