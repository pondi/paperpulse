<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\BankStatementResource;
use App\Models\BankStatement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BankStatementController extends BaseApiController
{
    /**
     * List bank statements with optional filtering
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'bank_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = BankStatement::where('user_id', $request->user()->id);

        if (! empty($validated['bank_name'])) {
            $query->where('bank_name', 'LIKE', "%{$validated['bank_name']}%");
        }

        if (! empty($validated['account_number'])) {
            $query->where('account_number', $validated['account_number']);
        }

        $query->with(['tags']);

        $statements = $query
            ->latest('statement_date')
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(BankStatementResource::collection($statements));
    }

    /**
     * Get detailed bank statement information
     */
    public function show(Request $request, int $id)
    {
        try {
            $statement = BankStatement::where('user_id', $request->user()->id)
                ->with(['transactions', 'tags', 'file'])
                ->findOrFail($id);

            return $this->success(
                new BankStatementResource($statement),
                'Bank statement details retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Bank statement not found');
        }
    }
}
