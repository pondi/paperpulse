<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request): Response
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with(['merchant', 'file'])
            ->orderBy('invoice_date', 'desc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_type' => $invoice->invoice_type,
                    'from_name' => $invoice->from_name,
                    'to_name' => $invoice->to_name,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'total_amount' => $invoice->total_amount,
                    'amount_due' => $invoice->amount_due,
                    'currency' => $invoice->currency,
                    'payment_status' => $invoice->payment_status,
                    'file_id' => $invoice->file_id,
                ];
            });

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display the specified invoice
     */
    public function show(Request $request, Invoice $invoice): Response
    {
        // Authorization check
        if ($invoice->user_id !== $request->user()->id) {
            abort(403);
        }

        $invoice->load(['merchant', 'file', 'tags', 'lineItems']);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_type' => $invoice->invoice_type,
                'from_name' => $invoice->from_name,
                'from_address' => $invoice->from_address,
                'from_vat_number' => $invoice->from_vat_number,
                'from_email' => $invoice->from_email,
                'from_phone' => $invoice->from_phone,
                'to_name' => $invoice->to_name,
                'to_address' => $invoice->to_address,
                'to_vat_number' => $invoice->to_vat_number,
                'to_email' => $invoice->to_email,
                'to_phone' => $invoice->to_phone,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'delivery_date' => $invoice->delivery_date?->format('Y-m-d'),
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'discount_amount' => $invoice->discount_amount,
                'shipping_amount' => $invoice->shipping_amount,
                'total_amount' => $invoice->total_amount,
                'amount_paid' => $invoice->amount_paid,
                'amount_due' => $invoice->amount_due,
                'currency' => $invoice->currency,
                'payment_method' => $invoice->payment_method,
                'payment_status' => $invoice->payment_status,
                'payment_terms' => $invoice->payment_terms,
                'notes' => $invoice->notes,
                'line_items' => $invoice->lineItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'line_number' => $item->line_number,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $item->tax_rate,
                        'tax_amount' => $item->tax_amount,
                        'total_amount' => $item->total_amount,
                    ];
                }),
                'file_id' => $invoice->file_id,
                'tags' => $invoice->tags,
            ],
        ]);
    }
}
