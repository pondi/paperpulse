<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'receipt_ids' => 'required|array|min:1',
            'receipt_ids.*' => ['integer', new ExistsForUser('receipts')],
            'category_id' => ['required_without:category', 'nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'category' => ['required_without:category_id', 'nullable', 'string', 'max:255', Rule::exists('categories', 'name')->where('user_id', $this->user()->id)->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_ids.required' => 'Select at least one receipt.',
            'category_id.required_without' => 'Select a category.',
            'category.required_without' => 'Select a category.',
            'category.exists' => 'Select one of your active categories.',
        ];
    }
}
