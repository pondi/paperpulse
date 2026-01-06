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

        // Build file information for preview/download
        $fileInfo = null;
        if ($contract->file) {
            $extension = $contract->file->fileExtension ?? 'pdf';
            $typeFolder = 'documents';

            // Check if there's an archive PDF available
            $hasArchivePdf = ! empty($contract->file->s3_archive_path);
            $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
            $pdfUrl = null;

            if ($hasPdfVariant) {
                $pdfUrl = route('documents.serve', [
                    'guid' => $contract->file->guid,
                    'type' => $typeFolder,
                    'extension' => 'pdf',
                    'variant' => $hasArchivePdf ? 'archive' : 'original',
                ]);
            }

            // Generate preview URL if available
            $previewUrl = null;
            if ($contract->file->has_image_preview && $contract->file->s3_image_path) {
                $previewUrl = route('documents.serve', [
                    'guid' => $contract->file->guid,
                    'type' => 'preview',
                    'extension' => 'jpg',
                ]);
            }

            $fileInfo = [
                'id' => $contract->file->id,
                'url' => route('documents.serve', [
                    'guid' => $contract->file->guid,
                    'type' => $typeFolder,
                    'extension' => $extension,
                ]),
                'pdfUrl' => $pdfUrl,
                'previewUrl' => $previewUrl,
                'extension' => $extension,
                'mime_type' => $contract->file->mime_type,
                'size' => $contract->file->fileSize,
                'guid' => $contract->file->guid,
                'has_preview' => $contract->file->has_image_preview,
                'is_pdf' => $hasPdfVariant,
                'uploaded_at' => $contract->file->uploaded_at?->toIso8601String(),
                'file_created_at' => $contract->file->file_created_at?->toIso8601String(),
                'file_modified_at' => $contract->file->file_modified_at?->toIso8601String(),
            ];
        }

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
                'file' => $fileInfo,
                'tags' => $contract->tags,
                'created_at' => $contract->created_at?->toIso8601String(),
                'updated_at' => $contract->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
