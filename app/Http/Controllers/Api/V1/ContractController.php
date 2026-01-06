<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ContractController extends BaseApiController
{
    /**
     * List contracts with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'contract_type' => 'nullable|string',
            'status' => 'nullable|string|in:draft,active,expired,terminated,renewed',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Contract::where('user_id', $request->user()->id);

        if (! empty($validated['contract_type'])) {
            $query->where('contract_type', $validated['contract_type']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->with(['tags']);

        $contracts = $query
            ->latest('effective_date')
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(ContractResource::collection($contracts));
    }

    /**
     * Get detailed contract information
     */
    public function show(Request $request, int $id)
    {
        try {
            $contract = Contract::where('user_id', $request->user()->id)
                ->with(['tags', 'file'])
                ->findOrFail($id);

            return $this->success(
                new ContractResource($contract),
                'Contract details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Contract not found');
        }
    }
}
