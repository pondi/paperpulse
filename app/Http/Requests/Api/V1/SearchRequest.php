<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:200',
            'query' => 'nullable|string|max:200',
            'type' => 'nullable|string|in:all,receipt,document,invoice,contract,voucher,warranty,return_policy,bank_statement',
            'limit' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1|max:20',
            'saved_search' => 'nullable|integer',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'amount_min' => 'nullable|numeric',
            'amount_max' => ['nullable', 'numeric', ...($this->filled('amount_min') ? ['gte:amount_min'] : [])],
            'category' => 'nullable|string|max:100',
            'document_type' => 'nullable|string|max:100',
            'collection_id' => ['nullable', 'integer', new ExistsForUser('collections')],
            'vendor' => 'nullable|string|max:100',
            'vendors' => 'nullable|array|max:20',
            'vendors.*' => 'string|max:100',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
            'amount_max.gte' => 'The maximum amount must be at least the minimum amount.',
        ];
    }
}
