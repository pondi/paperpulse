<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesEntityCrud;
use App\Http\Requests\EntityIndexRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Resources\Inertia\ContractInertiaResource;
use App\Models\Contract;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends BaseResourceController
{
    use HandlesEntityCrud;

    protected string $model = Contract::class;

    protected string $resource = 'Contracts';

    protected array $indexWith = ['file'];

    protected array $showWith = ['file', 'tags'];

    protected array $searchableFields = ['contract_number', 'contract_title', 'contract_type'];

    protected string $defaultSort = 'effective_date';

    /**
     * Display a listing of contracts.
     */
    public function index(Request $request): Response
    {
        $filters = app(EntityIndexRequest::class)->validated();
        $query = Contract::where('user_id', $request->user()->id)->with($this->indexWith);

        if ($search = $filters['search'] ?? null) {
            $this->applySearch($query, $search);
        }

        if ($value = $filters['status'] ?? null) {
            $query->where('status', $value);
        }

        if ($value = $filters['type'] ?? null) {
            $query->where('contract_type', $value);
        }

        $contracts = $query->orderBy($filters['sort'] ?? $this->defaultSort, $filters['sort_direction'] ?? $this->defaultSortDirection)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? $this->perPage)->withQueryString()
            ->through(fn (Contract $contract) => ContractInertiaResource::forIndex($contract)->toArray($request));

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts->items(),
            'filters' => $filters,
            'pagination' => [
                'links' => $contracts->linkCollection(),
                'from' => $contracts->firstItem(),
                'to' => $contracts->lastItem(),
                'total' => $contracts->total(),
            ],
        ]);
    }

    /**
     * Display the specified contract.
     */
    public function show($id): Response
    {
        $contract = Contract::accessibleBy(auth()->user())->with($this->showWith)
            ->with(['file' => fn ($query) => $query->withoutGlobalScope('user')])->findOrFail($id instanceof Contract ? $id->id : $id);

        $this->authorize('view', $contract);

        return Inertia::render('Contracts/Show', [
            'contract' => ContractInertiaResource::forShow($contract)->toArray(request()),
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Contracts', 'href' => route('contracts.index')],
                ['label' => $contract->contract_title ?? 'Contract #'.$contract->id],
            ],
        ]);
    }

    /**
     * Transform item for index display.
     */
    protected function transformForIndex(Model $item): array
    {
        return ContractInertiaResource::forIndex($item)->toArray(request());
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow(Model $item): array
    {
        return ContractInertiaResource::forShow($item)->toArray(request());
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $item = $id instanceof Contract ? $id : Contract::findOrFail($id);
        $this->authorize('update', $item);
        $validated = app(UpdateContractRequest::class)->validated();

        $item->getConnection()->transaction(function () use ($item, $validated): void {
            $item->update($validated);
            $file = $item->file;
            $meta = $file->meta ?? [];
            $meta['manual_edits']['contract'] = array_merge($meta['manual_edits']['contract'] ?? [], $validated);
            $file->update(['meta' => $meta]);
        });

        return $this->afterUpdate($item, $request);
    }

    public function download(Contract $contract): mixed
    {
        return $this->entityDownload($contract);
    }

    public function destroy($id): mixed
    {
        $contract = $id instanceof Contract
            ? $id
            : Contract::findOrFail($id);

        return $this->entityDestroy($contract);
    }

    public function attachTag(Request $request, Contract $contract): mixed
    {
        return $this->entityAttachTag($request, $contract);
    }

    public function detachTag(Contract $contract, Tag $tag): mixed
    {
        return $this->entityDetachTag($contract, $tag);
    }

    protected function getRouteName(): string
    {
        return 'contracts';
    }
}
