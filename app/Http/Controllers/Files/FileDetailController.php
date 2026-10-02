<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WarrantyResource;
use App\Http\Resources\FileExtractionReportResource;
use App\Http\Resources\Inertia\BankStatementInertiaResource;
use App\Http\Resources\Inertia\ContractInertiaResource;
use App\Http\Resources\Inertia\DocumentInertiaResource;
use App\Http\Resources\Inertia\FileInertiaResource;
use App\Http\Resources\Inertia\InvoiceInertiaResource;
use App\Http\Resources\Inertia\ReceiptInertiaResource;
use App\Http\Resources\Inertia\VoucherInertiaResource;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Receipt;
use App\Services\Files\FileDetailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FileDetailController extends Controller
{
    public function show(Request $request, File $file, FileDetailService $details): Response
    {
        $this->authorize('view', $file);
        $details->loadExtractedEntities($file);
        $entities = $file->extractableEntities->filter(fn (ExtractableEntity $extraction): bool => $extraction->entity !== null)
            ->map(function (ExtractableEntity $extraction) use ($request): array {
                $entity = $extraction->entity;
                $data = match ($entity->getMorphClass()) {
                    'receipt' => ReceiptInertiaResource::forIndex($entity)->toArray($request),
                    'document' => DocumentInertiaResource::forIndex($entity)->toArray($request),
                    'invoice' => InvoiceInertiaResource::forIndex($entity)->toArray($request),
                    'contract' => ContractInertiaResource::forIndex($entity)->toArray($request),
                    'voucher' => VoucherInertiaResource::forIndex($entity)->toArray($request),
                    'bank_statement' => BankStatementInertiaResource::forIndex($entity)->toArray($request),
                    'warranty' => (new WarrantyResource($entity))->toArray($request),
                    'return_policy' => $entity->only(['id', 'return_deadline', 'exchange_deadline', 'conditions', 'refund_method', 'restocking_fee', 'is_final_sale', 'requires_receipt', 'requires_original_packaging']),
                };

                return [
                    'entity_type' => $entity->getMorphClass(), 'entity_id' => $entity->id,
                    'is_primary' => $extraction->is_primary, 'confidence_score' => $extraction->confidence_score,
                    'entity' => $data,
                ];
            })->values();
        $fileData = FileInertiaResource::forShow($file)->toArray($request);
        $fileData['can_view_extraction_report'] = $file->user_id === $request->user()->id;
        if ($fileData['can_view_extraction_report']) {
            $fileData['extraction'] = (new FileExtractionReportResource($file))->toArray($request)['extraction'];
        }
        if ($entities->isEmpty()) {
            $fileData['primary_receipt'] = Receipt::withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id)->first(['id']);
            $fileData['primary_document'] = Document::withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id)->first(['id']);
        }

        return Inertia::render('Files/Show', [
            'file' => $fileData, 'extractedEntities' => $entities,
            'hasLegacyData' => isset($fileData['primary_receipt']) || isset($fileData['primary_document']),
        ]);
    }
}
