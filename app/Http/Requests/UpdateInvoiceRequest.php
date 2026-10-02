<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $invoice = $invoice instanceof Invoice ? $invoice : Invoice::findOrFail($invoice);
        $isCreditNote = $this->input('invoice_type', $invoice->invoice_type) === 'credit_note';

        return [
            'invoice_number' => ['sometimes', 'string', 'max:255'],
            'from_name' => ['sometimes', 'string', 'max:255'],
            'to_name' => ['sometimes', 'string', 'max:255'],
            'invoice_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'invoice_type' => ['sometimes', Rule::in(['invoice', 'credit_note', 'debit_note', 'proforma'])],
            'total_amount' => ['sometimes', 'numeric', $isCreditNote ? 'max:0' : 'min:0'],
            'amount_paid' => ['sometimes', 'numeric', $isCreditNote ? 'max:0' : 'min:0'],
            'payment_status' => ['sometimes', Rule::in(['paid', 'unpaid', 'partial', 'overdue'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $invoice = $this->route('invoice');
            $invoice = $invoice instanceof Invoice ? $invoice : Invoice::findOrFail($invoice);
            $total = (float) $this->input('total_amount', $invoice->total_amount);
            $paid = (float) $this->input('amount_paid', $invoice->amount_paid);
            $status = $this->input('payment_status', $invoice->payment_status);
            $isCreditNote = $this->input('invoice_type', $invoice->invoice_type) === 'credit_note';
            if (($isCreditNote && $total > 0) || (! $isCreditNote && $total < 0)) {
                $validator->errors()->add('total_amount', 'Only credit notes may have a negative total.');
            }
            if ($status === 'partial' && ($total * $paid <= 0 || abs($paid) >= abs($total))) {
                $validator->errors()->add('amount_paid', 'A partial payment must be between zero and the invoice total.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'payment_status.in' => 'Choose a supported payment status.',
            'invoice_type.in' => 'Choose a supported invoice type.',
            'total_amount.min' => 'Invoice totals must be nonnegative.',
            'total_amount.max' => 'Credit note totals must be nonpositive.',
            'amount_paid.min' => 'Invoice payments must be nonnegative.',
            'amount_paid.max' => 'Credit note payments must be nonpositive.',
        ];
    }
}
