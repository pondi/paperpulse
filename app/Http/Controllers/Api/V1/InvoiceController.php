<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    /**
     * List invoices with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'invoice_type' => 'nullable|string|in:invoice,credit_note,debit_note,proforma',
            'payment_status' => 'nullable|string|in:paid,unpaid,partial,overdue',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Invoice::where('user_id', $request->user()->id);

        if (! empty($validated['invoice_type'])) {
            $query->where('invoice_type', $validated['invoice_type']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        $query->with(['merchant', 'category', 'tags']);

        $invoices = $query
            ->latest('invoice_date')
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(InvoiceResource::collection($invoices));
    }

    /**
     * Get detailed invoice information
     */
    public function show(Request $request, int $id)
    {
        try {
            $invoice = Invoice::where('user_id', $request->user()->id)
                ->with(['merchant', 'category', 'lineItems', 'tags', 'file'])
                ->findOrFail($id);

            return $this->success(
                new InvoiceResource($invoice),
                'Invoice details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Invoice not found');
        }
    }
}
