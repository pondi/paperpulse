<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\WarrantyResource;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class WarrantyController extends BaseApiController
{
    /**
     * List warranties with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'manufacturer' => 'nullable|string',
            'product_category' => 'nullable|string',
            'warranty_type' => 'nullable|string|in:manufacturer,extended,store,third_party',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Warranty::where('user_id', $request->user()->id);

        if (! empty($validated['manufacturer'])) {
            $query->where('manufacturer', 'LIKE', "%{$validated['manufacturer']}%");
        }

        if (! empty($validated['product_category'])) {
            $query->where('product_category', $validated['product_category']);
        }

        if (! empty($validated['warranty_type'])) {
            $query->where('warranty_type', $validated['warranty_type']);
        }

        $query->with(['tags']);

        $warranties = $query
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(WarrantyResource::collection($warranties));
    }

    /**
     * Get detailed warranty information
     */
    public function show(Request $request, int $id)
    {
        try {
            $warranty = Warranty::where('user_id', $request->user()->id)
                ->with(['tags', 'file', 'receipt', 'invoice'])
                ->findOrFail($id);

            return $this->success(
                new WarrantyResource($warranty),
                'Warranty details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Warranty not found');
        }
    }
}
