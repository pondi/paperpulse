<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoices\InvoiceTransformer;
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
            ->withCount('lineItems')
            ->orderBy('invoice_date', 'desc')
            ->get()
            ->map(fn (Invoice $invoice) => InvoiceTransformer::forIndex($invoice));

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
            'invoice' => InvoiceTransformer::forShow($invoice),
        ]);
    }
}
