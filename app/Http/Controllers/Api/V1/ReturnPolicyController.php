<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\ReturnPolicyResource;
use App\Models\ReturnPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ReturnPolicyController extends BaseApiController
{
    /**
     * List return policies with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'refund_method' => 'nullable|string|in:full_refund,store_credit,exchange_only,no_refund',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ReturnPolicy::where('user_id', $request->user()->id);

        if (! empty($validated['refund_method'])) {
            $query->where('refund_method', $validated['refund_method']);
        }

        $query->with(['merchant', 'tags']);

        $policies = $query
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(ReturnPolicyResource::collection($policies));
    }

    /**
     * Get detailed return policy information
     */
    public function show(Request $request, int $id)
    {
        try {
            $policy = ReturnPolicy::where('user_id', $request->user()->id)
                ->with(['merchant', 'tags', 'file', 'receipt', 'invoice'])
                ->findOrFail($id);

            return $this->success(
                new ReturnPolicyResource($policy),
                'Return policy details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Return policy not found');
        }
    }
}
