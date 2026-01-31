<?php

namespace App\Http\Controllers;

use App\Http\Resources\Inertia\ContractInertiaResource;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    /**
     * Display a listing of contracts
     */
    public function index(Request $request): Response
    {
        $contracts = Contract::where('user_id', $request->user()->id)
            ->orderBy('effective_date', 'desc')
            ->get()
            ->map(fn (Contract $contract) => ContractInertiaResource::forIndex($contract));

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Display the specified contract
     */
    public function show(Request $request, Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load(['file', 'tags']);

        return Inertia::render('Contracts/Show', [
            'contract' => ContractInertiaResource::forShow($contract),
        ]);
    }
}
