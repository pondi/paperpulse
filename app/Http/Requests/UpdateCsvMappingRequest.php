<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCsvMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = $this->route('file');

        return $file instanceof File && $file->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        $rules = ['mapping' => ['required', 'array:transaction_date,posting_date,description,amount,debit,credit,balance,reference,counterparty,type,currency,date_format']];
        foreach (['transaction_date', 'posting_date', 'description', 'amount', 'debit', 'credit', 'balance', 'reference', 'counterparty', 'type', 'currency'] as $field) {
            $rules['mapping.'.$field] = [in_array($field, ['transaction_date', 'description'], true) ? 'required' : 'nullable', 'integer', 'min:0'];
        }
        $rules['mapping.date_format'] = ['nullable', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'd-m-Y'])];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'mapping.required' => 'Select the columns for this CSV before retrying.',
            'mapping.*.integer' => 'Choose a CSV column from the list.',
            'mapping.*.min' => 'Choose an existing CSV column.',
            'mapping.date_format.in' => 'Choose one of the supported date formats.',
        ];
    }
}
