<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:invoice_date,due_date,total_amount,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'payment_status' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = Invoice::query()
            ->with(['merchant', 'category', 'lineItems', 'tags']);

        if (! empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('invoice_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('invoice_date', '<=', $validated['date_to']);
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $invoices = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(InvoiceResource::collection($invoices), 'Invoices retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->with(['merchant', 'category', 'lineItems', 'tags'])
            ->find($id);

        if (! $invoice) {
            return $this->notFound('Invoice not found');
        }

        return $this->success(new InvoiceResource($invoice), 'Invoice retrieved');
    }
}
