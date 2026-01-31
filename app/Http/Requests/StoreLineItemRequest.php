<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLineItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'A description is required.',
            'text.max' => 'Description cannot exceed 255 characters.',
            'sku.max' => 'SKU cannot exceed 100 characters.',
            'qty.required' => 'Quantity is required.',
            'qty.numeric' => 'Quantity must be a number.',
            'qty.min' => 'Quantity cannot be negative.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'price.min' => 'Price cannot be negative.',
            'total.required' => 'Total is required.',
            'total.numeric' => 'Total must be a number.',
            'total.min' => 'Total cannot be negative.',
        ];
    }
}
