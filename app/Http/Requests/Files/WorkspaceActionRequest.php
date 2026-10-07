<?php

namespace App\Http\Requests\Files;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class WorkspaceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,flag,delete,tag,save'],
            'file_ids' => ['required', 'array', 'min:1', 'max:50'],
            'file_ids.*' => ['required', 'integer', 'distinct', new ExistsForUser('files')],
            'tag_id' => ['required_if:action,tag', 'nullable', 'integer', new ExistsForUser('tags')],
            'entity_type' => ['required_if:action,save', 'in:receipt,document'],
            'entity_id' => ['required_if:action,save', 'integer'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required_if:entity_type,receipt', 'nullable', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'receipt_date' => ['required_if:entity_type,receipt', 'nullable', 'date'],
            'currency' => ['required_if:entity_type,receipt', 'nullable', 'string', 'size:3'],
            'title' => ['required_if:entity_type,document', 'nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', new ExistsForUser('categories')],
            'line_items' => ['sometimes', 'array', 'max:500'],
            'line_items.*.id' => ['required', 'integer', 'distinct'],
            'line_items.*.text' => ['required', 'string', 'max:255'],
            'line_items.*.qty' => ['required', 'numeric', 'min:0'],
            'line_items.*.price' => ['required', 'numeric', 'min:0'],
            'line_items.*.total' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return ['file_ids.required' => 'Select at least one file.',
            'tag_id.required_if' => 'Choose a tag to apply.',
            'line_items.*.text.required' => 'Each line item needs a description.',
            'currency.size' => 'Use a three-letter currency code.'];
    }
}
