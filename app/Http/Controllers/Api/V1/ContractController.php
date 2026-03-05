<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:effective_date,expiry_date,contract_value,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'status' => 'nullable|string',
            'contract_type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = Contract::query()
            ->with(['tags']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['contract_type'])) {
            $query->where('contract_type', $validated['contract_type']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('effective_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('effective_date', '<=', $validated['date_to']);
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $contracts = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(ContractResource::collection($contracts), 'Contracts retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $contract = Contract::query()
            ->with(['tags'])
            ->find($id);

        if (! $contract) {
            return $this->notFound('Contract not found');
        }

        return $this->success(new ContractResource($contract), 'Contract retrieved');
    }
}
