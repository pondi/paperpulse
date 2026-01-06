<?php

namespace App\Http\Controllers;

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
            ->with(['file'])
            ->orderBy('effective_date', 'desc')
            ->get()
            ->map(function ($contract) {
                return [
                    'id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'contract_title' => $contract->contract_title,
                    'contract_type' => $contract->contract_type,
                    'effective_date' => $contract->effective_date?->format('Y-m-d'),
                    'expiry_date' => $contract->expiry_date?->format('Y-m-d'),
                    'contract_value' => $contract->contract_value,
                    'currency' => $contract->currency,
                    'status' => $contract->status,
                    'file_id' => $contract->file_id,
                ];
            });

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Display the specified contract
     */
    public function show(Request $request, Contract $contract): Response
    {
        // Authorization check
        if ($contract->user_id !== $request->user()->id) {
            abort(403);
        }

        $contract->load(['file', 'tags']);

        return Inertia::render('Contracts/Show', [
            'contract' => [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'contract_title' => $contract->contract_title,
                'contract_type' => $contract->contract_type,
                'parties' => $contract->parties,
                'effective_date' => $contract->effective_date?->format('Y-m-d'),
                'expiry_date' => $contract->expiry_date?->format('Y-m-d'),
                'signature_date' => $contract->signature_date?->format('Y-m-d'),
                'duration' => $contract->duration,
                'renewal_terms' => $contract->renewal_terms,
                'termination_conditions' => $contract->termination_conditions,
                'contract_value' => $contract->contract_value,
                'currency' => $contract->currency,
                'payment_schedule' => $contract->payment_schedule,
                'governing_law' => $contract->governing_law,
                'jurisdiction' => $contract->jurisdiction,
                'status' => $contract->status,
                'key_terms' => $contract->key_terms,
                'obligations' => $contract->obligations,
                'summary' => $contract->summary,
                'file_id' => $contract->file_id,
                'tags' => $contract->tags,
            ],
        ]);
    }
}
