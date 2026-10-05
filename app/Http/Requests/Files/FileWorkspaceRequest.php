<?php

namespace App\Http\Requests\Files;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FileWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'return_to' => ['nullable', 'string', 'max:2048', 'regex:#^/(?:library|search)(?:\?[^\r\n]*)?$#'],
        ];
    }

    public function messages(): array
    {
        return ['return_to.regex' => 'Return to a page in your library or search results.'];
    }
}
