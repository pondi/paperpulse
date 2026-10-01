<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Http\Controllers\Concerns\HandlesEntityCrud;
use App\Http\Requests\BankTransactionIndexRequest;
use App\Http\Resources\BankTransactionResource;
use App\Http\Resources\Inertia\BankStatementInertiaResource;
use App\Models\BankStatement;
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

    protected array $showWith = ['file', 'tags'];

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
        $statement = BankStatement::query()->accessibleBy(auth()->user())
            ->with(['file' => fn ($query) => $query->withoutGlobalScope('user'), 'tags'])
            ->findOrFail($id);

        $this->authorize('view', $statement);

        return Inertia::render('BankStatements/Show', [
            'statement' => BankStatementInertiaResource::forShow($statement)->toArray(request()),
            'transactions' => $this->transactionPage($statement, []),
            'transaction_stats' => [
                'credit_count' => $statement->transactions()->withoutGlobalScope('user')->where('amount', '>', 0)->count(),
                'debit_count' => $statement->transactions()->withoutGlobalScope('user')->where('amount', '<', 0)->count(),
            ],
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
            'category_groups' => collect(TransactionCategory::cases())->map(fn (TransactionCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Bank Statements', 'href' => route('bank-statements.index')],
                ['label' => $statement->bank_name ?? 'Statement #'.$statement->id],
            ],
        ]);
    }

    /**
     * Return paginated, filterable transactions for a statement as JSON.
     */
    public function transactions(BankTransactionIndexRequest $request, int $id): JsonResponse
    {
        $statement = BankStatement::query()->accessibleBy($request->user())->findOrFail($id);
        $this->authorize('view', $statement);

        return response()->json($this->transactionPage($statement, $request->validated()));
    }

    /** @return array<string, mixed> */
    private function transactionPage(BankStatement $statement, array $filters): array
    {
        $query = $statement->transactions()->withoutGlobalScope('user');
        foreach (['type' => 'transaction_type', 'category_group' => 'category_group'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('counterparty_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }
        $transactions = $query->orderBy($filters['sort'] ?? 'transaction_date', $filters['sort_direction'] ?? 'desc')
            ->orderBy('id', $filters['sort_direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        return BankTransactionResource::collection($transactions)->response()->getData(true);
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
