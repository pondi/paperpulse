<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesEntityCrud;
use App\Http\Resources\Inertia\ContractInertiaResource;
use App\Models\Contract;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends BaseResourceController
{
    use HandlesEntityCrud;

    protected string $model = Contract::class;

    protected string $resource = 'Contracts';

    protected array $showWith = ['file', 'tags'];

    protected array $searchableFields = ['contract_number', 'contract_title', 'contract_type'];

    protected string $defaultSort = 'effective_date';

    protected array $validationRules = [
        'contract_title' => 'sometimes|string|max:255',
        'contract_type' => 'sometimes|string|max:100',
        'effective_date' => 'sometimes|date',
        'expiry_date' => 'nullable|date',
        'contract_value' => 'nullable|numeric|min:0',
        'status' => 'sometimes|string|max:50',
        'summary' => 'nullable|string|max:2000',
        'governing_law' => 'nullable|string|max:255',
        'jurisdiction' => 'nullable|string|max:255',
    ];

    /**
     * Display a listing of contracts.
     */
    public function index(Request $request): Response
    {
        $contracts = Contract::where('user_id', $request->user()->id)
            ->orderBy($this->defaultSort, $this->defaultSortDirection)
            ->get()
            ->map(fn (Contract $contract) => ContractInertiaResource::forIndex($contract)->toArray(request()));

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Display the specified contract.
     */
    public function show($id): Response
    {
        $contract = Contract::with($this->showWith)->findOrFail($id);

        $this->authorize('view', $contract);

        return Inertia::render('Contracts/Show', [
            'contract' => ContractInertiaResource::forShow($contract)->toArray(request()),
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
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
