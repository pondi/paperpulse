<?php

namespace App\Http\Requests\Api\V1;

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
            'type' => 'nullable|string|in:all,receipt,document',
            'limit' => 'nullable|integer|min:1|max:50',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'amount_min' => 'nullable|numeric',
            'amount_max' => 'nullable|numeric',
            'category' => 'nullable|string|max:100',
            'document_type' => 'nullable|string|max:100',
            'tags' => 'nullable',
        ];
    }
}
