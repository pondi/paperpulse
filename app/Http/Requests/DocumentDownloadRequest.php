<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class DocumentDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['ids' => ['required', 'array', 'max:10000'], 'ids.*' => ['integer', 'distinct', new ExistsForUser('files')]];
    }

    public function messages(): array
    {
        return ['ids.required' => 'Select at least one document.', 'ids.max' => 'Select at most 10000 documents per operation.',
            'ids.*.distinct' => 'Select each document only once.'];
    }
}
