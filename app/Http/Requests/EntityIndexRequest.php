<?php

namespace App\Http\Requests;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ] + match ($this->route()->getName()) {
            'invoices.index' => [
                'sort' => ['sometimes', Rule::in(['invoice_date', 'due_date', 'total_amount', 'invoice_number'])],
                'paymentStatus' => ['nullable', Rule::in(['paid', 'unpaid', 'partial', 'overdue'])],
                'type' => ['nullable', Rule::in(['invoice', 'credit_note', 'debit_note', 'proforma'])],
            ],
            'contracts.index' => [
                'sort' => ['sometimes', Rule::in(['effective_date', 'expiry_date', 'contract_title', 'contract_value'])],
                'status' => ['nullable', Rule::in(['draft', 'active', 'expired', 'terminated', 'renewed'])],
                'type' => ['nullable', Rule::in(['employment', 'service', 'rental', 'purchase', 'nda'])],
            ],
            'vouchers.index' => [
                'sort' => ['sometimes', Rule::in(['expiry_date', 'issue_date', 'code', 'current_value'])],
                'status' => ['nullable', Rule::in(['active', 'expired', 'redeemed'])],
                'type' => ['nullable', Rule::in(['gift_card', 'payment_plan', 'store_credit', 'coupon'])],
            ],
            'receipts.index' => [
                'category_id' => ['nullable', 'integer', new ExistsForUser('categories')],
                'merchant_id' => ['nullable', 'integer', new ExistsForUser('merchants')],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'per_page.between' => 'Choose between 1 and 100 results per page.',
            'sort.in' => 'Choose a supported sort field.',
            'type.in' => 'Choose a supported document type.',
            'status.in' => 'Choose a supported status.',
            'paymentStatus.in' => 'Choose a supported payment status.',
        ];
    }
}
