<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseFoldersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)->whereNull('deleted_at'))],
            'search' => ['nullable', 'string', 'max:255'],
            'archived' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:name,-name,files,-files,created,-created'],
        ];
    }

    public function messages(): array
    {
        return ['parent_id.exists' => 'Select an active folder you own.', 'per_page.max' => 'Request at most 100 folders at a time.'];
    }
}
