<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\WarrantyResource;
use App\Models\Warranty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:warranty_end_date,purchase_date,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'warranty_type' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
        ]);

        $query = Warranty::query()
            ->with(['tags']);

        if (! empty($validated['warranty_type'])) {
            $query->where('warranty_type', $validated['warranty_type']);
        }

        if (! empty($validated['manufacturer'])) {
            $query->where('manufacturer', 'like', '%'.$validated['manufacturer'].'%');
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $warranties = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(WarrantyResource::collection($warranties), 'Warranties retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $warranty = Warranty::query()
            ->with(['tags'])
            ->find($id);

        if (! $warranty) {
            return $this->notFound('Warranty not found');
        }

        return $this->success(new WarrantyResource($warranty), 'Warranty retrieved');
    }
}
