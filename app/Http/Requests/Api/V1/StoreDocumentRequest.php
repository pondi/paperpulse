<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_id' => 'required|exists:files,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string|max:50',
            'content' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'extracted_text' => 'nullable|array',
            'entities' => 'nullable|array',
            'ai_entities' => 'nullable|array',
            'ai_summary' => 'nullable|string',
            'metadata' => 'nullable|array',
            'language' => 'nullable|string|max:10',
            'document_date' => 'nullable|date',
            'page_count' => 'nullable|integer|min:1',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file_id.required' => 'A file must be associated with the document.',
            'file_id.exists' => 'The selected file does not exist.',
            'title.required' => 'The document title is required.',
            'document_type.required' => 'The document type is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'tag_ids.*.exists' => 'One or more selected tags do not exist.',
        ];
    }
}
