<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Http\Controllers\Concerns\HandlesEntityCrud;
use App\Http\Resources\BankTransactionResource;
use App\Http\Resources\Inertia\BankStatementInertiaResource;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankStatementController extends BaseResourceController
{
    use HandlesEntityCrud;

    protected string $model = BankStatement::class;

    protected string $resource = 'BankStatements';

    protected array $indexWith = [];

    protected array $showWith = ['transactions', 'file', 'tags'];

    protected array $searchableFields = ['bank_name', 'account_holder_name', 'account_number'];

    protected string $defaultSort = 'statement_date';

    protected array $validationRules = [
        'bank_name' => 'sometimes|string|max:255',
        'account_holder_name' => 'sometimes|string|max:500',
        'notes' => 'nullable|string|max:2000',
    ];

    /**
     * Display a listing of bank statements.
     */
    public function index(Request $request): Response
    {
        $statements = BankStatement::where('user_id', $request->user()->id)
            ->with($this->indexWith)
            ->orderBy($this->defaultSort, $this->defaultSortDirection)
            ->get()
            ->map(fn (BankStatement $statement) => BankStatementInertiaResource::forIndex($statement)->toArray(request()));

        return Inertia::render('BankStatements/Index', [
            'statements' => $statements,
        ]);
    }

    /**
     * Display the specified bank statement.
     */
    public function show($id): Response
    {
        $statement = BankStatement::with($this->showWith)->findOrFail($id);

        $this->authorize('view', $statement);

        return Inertia::render('BankStatements/Show', [
            'statement' => BankStatementInertiaResource::forShow($statement)->toArray(request()),
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
            'category_groups' => collect(TransactionCategory::cases())->map(fn (TransactionCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
        ]);
    }

    /**
     * Return paginated, filterable transactions for a statement as JSON.
     */
    public function transactions(Request $request, int $id): JsonResponse
    {
        $statement = BankStatement::findOrFail($id);
        $this->authorize('view', $statement);

        $query = BankTransaction::where('bank_statement_id', $statement->id);

        if ($type = $request->input('type')) {
            $query->where('transaction_type', $type);
        }

        if ($categoryGroup = $request->input('category_group')) {
            $query->where('category_group', $categoryGroup);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('counterparty_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if ($from = $request->input('date_from')) {
            $query->where('transaction_date', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->where('transaction_date', '<=', $to);
        }

        $sortField = $request->input('sort', 'transaction_date');
        $sortDirection = in_array(strtolower($request->input('sort_direction', 'desc')), ['asc', 'desc'], true)
            ? strtolower($request->input('sort_direction', 'desc'))
            : 'desc';
        $allowedSorts = ['transaction_date', 'amount', 'balance_after', 'description', 'category_group'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = min((int) $request->input('per_page', 50), 200);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'data' => $transactions->through(fn ($tx) => (new BankTransactionResource($tx))->resolve()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    protected function transformForIndex(Model $item): array
    {
        return BankStatementInertiaResource::forIndex($item)->toArray(request());
    }

    protected function transformForShow(Model $item): array
    {
        return BankStatementInertiaResource::forShow($item)->toArray(request());
    }

    public function download(BankStatement $bankStatement): mixed
    {
        return $this->entityDownload($bankStatement);
    }

    public function destroy($id): mixed
    {
        $statement = BankStatement::findOrFail($id);

        return $this->entityDestroy($statement);
    }

    public function attachTag(Request $request, BankStatement $bankStatement): mixed
    {
        return $this->entityAttachTag($request, $bankStatement);
    }

    public function detachTag(BankStatement $bankStatement, Tag $tag): mixed
    {
        return $this->entityDetachTag($bankStatement, $tag);
    }

    protected function getRouteName(): string
    {
        return 'bank-statements';
    }
}
