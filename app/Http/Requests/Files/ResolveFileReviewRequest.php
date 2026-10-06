<?php

namespace App\Http\Requests\Files;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;

class ResolveFileReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $file = $this->route('file');

        return $file instanceof File && $file->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return ['confirmed' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return ['confirmed.required' => 'Confirm that you checked the extracted values against the source.',
            'confirmed.accepted' => 'Confirm that you checked the extracted values against the source.'];
    }
}
