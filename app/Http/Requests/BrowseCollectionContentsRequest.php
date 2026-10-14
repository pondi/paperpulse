<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BrowseCollectionContentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('collection')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:name,-name,created,-created'],
            'files_page' => ['nullable', 'integer', 'min:1'],
            'children_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'Keep your folder search to 255 characters or fewer.',
            'sort.in' => 'Choose one of the available content sort orders.',
            'files_page.min' => 'Choose a file page starting at 1.',
            'children_page.min' => 'Choose a subfolder page starting at 1.',
        ];
    }
}
