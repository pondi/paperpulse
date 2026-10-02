<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolveDuplicateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('duplicateFlag'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['delete_file_id' => ['required', 'integer']];
    }

    public function messages(): array
    {
        return [
            'delete_file_id.required' => 'Choose a file to delete.',
            'delete_file_id.integer' => 'Choose a valid file to delete.',
        ];
    }
}
