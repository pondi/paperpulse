<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\BankStatementResource;
use App\Models\BankStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankStatementController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:statement_date,opening_balance,closing_balance,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'bank_name' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = BankStatement::query()
            ->with(['tags']);

        if (! empty($validated['bank_name'])) {
            $query->where('bank_name', 'like', '%'.$validated['bank_name'].'%');
        }

        if (! empty($validated['date_from'])) {
            $query->where('statement_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('statement_date', '<=', $validated['date_to']);
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $statements = $query->paginate($validated['per_page'] ?? 25);

        return $this->paginated(BankStatementResource::collection($statements), 'Bank statements retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $statement = BankStatement::query()
            ->with(['transactions', 'tags'])
            ->find($id);

        if (! $statement) {
            return $this->notFound('Bank statement not found');
        }

        return $this->success(new BankStatementResource($statement), 'Bank statement retrieved');
    }
}
