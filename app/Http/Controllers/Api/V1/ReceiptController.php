<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:receipt_date,total_amount,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'merchant' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'currency' => 'nullable|string|max:3',
        ]);

        $query = Receipt::query()
            ->with(['file', 'merchant', 'category', 'lineItems', 'tags']);

        if (! empty($validated['merchant'])) {
            $query->whereHas('merchant', function ($q) use ($validated) {
                $q->where('name', 'like', '%'.$validated['merchant'].'%');
            });
        }

        if (! empty($validated['date_from'])) {
            $query->where('receipt_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('receipt_date', '<=', $validated['date_to']);
        }

        if (! empty($validated['currency'])) {
            $query->where('currency', $validated['currency']);
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $receipts = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(ReceiptResource::collection($receipts), 'Receipts retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $receipt = Receipt::query()
            ->with(['file', 'merchant', 'category', 'lineItems', 'tags'])
            ->find($id);

        if (! $receipt) {
            return $this->notFound('Receipt not found');
        }

        return $this->success(new ReceiptResource($receipt), 'Receipt retrieved');
    }
}
