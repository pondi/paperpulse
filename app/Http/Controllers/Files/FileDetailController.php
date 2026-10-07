<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\FileWorkspaceRequest;
use App\Http\Resources\Api\V1\WarrantyResource;
use App\Http\Resources\FileExtractionReportResource;
use App\Http\Resources\Inertia\BankStatementInertiaResource;
use App\Http\Resources\Inertia\ContractInertiaResource;
use App\Http\Resources\Inertia\DocumentInertiaResource;
use App\Http\Resources\Inertia\FileInertiaResource;
use App\Http\Resources\Inertia\InvoiceInertiaResource;
use App\Http\Resources\Inertia\ReceiptInertiaResource;
use App\Http\Resources\Inertia\VoucherInertiaResource;
use App\Jobs\Files\GenerateFilePreview;
use App\Models\Document;
use App\Models\ExtractableEntity;
use App\Models\File;
use App\Models\Receipt;
use App\Services\Files\FileDetailService;
use App\Services\Files\StoragePathBuilder;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class FileDetailController extends Controller
{
    public function show(FileWorkspaceRequest $request, File $file, FileDetailService $details): Response|\Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $file);
        if (! $file->has_image_preview && in_array($file->status, ['completed', 'needs_review'], true)
            && (StoragePathBuilder::pdfVariant($file) !== null || in_array(strtolower((string) $file->fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true))
            && Cache::add('file-preview-repair:'.$file->id, true, now()->addMinutes(30))) {
            GenerateFilePreview::dispatch($file->id)->afterCommit();
        }
        $details->loadExtractedEntities($file);
        $legacyData = $file->extractableEntities->isEmpty();
        if ($legacyData) {
            foreach ([Receipt::class, Document::class] as $model) {
                foreach ($model::withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id)->get() as $entity) {
                    $extraction = new ExtractableEntity(['entity_type' => $entity->getMorphClass(), 'entity_id' => $entity->id, 'is_primary' => true]);
                    $extraction->setRelation('entity', $entity);
                    $file->extractableEntities->push($extraction);
                }
            }
        }
        $file->load('tags');
        foreach ($file->extractableEntities as $extraction) {
            if ($entity = $extraction->entity) {
                $entity->setRelation('file', $file);
                if ($entity instanceof Receipt || $entity instanceof Document) {
                    if ($file->user_id === $request->user()->id) {
                        $entity->loadMissing('sharedUsers');
                    } else {
                        $entity->setRelation('sharedUsers', collect());
                    }
                    $entity->loadMissing(['category' => fn ($query) => $query->withoutGlobalScope('user')]);
                }
                if ($entity instanceof Receipt) {
                    $entity->loadMissing(['merchant' => fn ($query) => $query->withoutGlobalScope('user')]);
                }
                if ($entity instanceof Receipt) {
                    $entity->loadMissing('lineItems');
                }
            }
        }
        $file->load(['processingJobs' => fn ($query) => $query->latest('id')->limit(1)->with('tasks')]);
        $entities = $file->extractableEntities->filter(fn (ExtractableEntity $extraction): bool => $extraction->entity !== null)
            ->map(function (ExtractableEntity $extraction) use ($request): array {
                $entity = $extraction->entity;
                $data = match ($entity->getMorphClass()) {
                    'receipt' => ReceiptInertiaResource::forIndex($entity)->toArray($request),
                    'document' => [...DocumentInertiaResource::forIndex($entity)->toArray($request), ...$entity->only(['description', 'summary', 'document_type', 'document_date', 'category_id'])],
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
                    'shares' => $entity->user_id === $request->user()->id && $entity->relationLoaded('sharedUsers')
                        ? $entity->sharedUsers->map(fn ($user): array => ['id' => $user->id, 'shared_with_user' => $user->only(['id', 'name', 'email']), 'permission' => $user->pivot->permission ?? 'view'])->values() : [],
                ];
            })->values();
        $fileData = FileInertiaResource::forShow($file)->toArray($request);
        $fileData['preview_pending'] = ! $file->has_image_preview && Cache::get('file-preview-repair:'.$file->id) === true;
        $fileData['can_view_extraction_report'] = $file->user_id === $request->user()->id;
        $fileData['back_url'] = $request->validated('return_to') ?: route('library.index');
        $fileData['back_label'] = str_starts_with($request->validated('return_to') ?? '', '/search') ? 'Back to search' : 'Back to Library';
        $fileData['note'] = $file->note;
        $fileData['can_edit'] = $file->user_id === $request->user()->id;
        $fileData['available_collections'] = $fileData['can_edit'] ? app(\App\Services\CollectionService::class)->getActiveCollectionsForSelector($request->user()->id) : [];
        $fileData['categories'] = $fileData['can_edit'] ? $request->user()->categories()->ordered()->get(['id', 'name']) : [];
        $fileData['collections'] = $file->collections()->accessibleBy($request->user())->get(['collections.id', 'collections.name']);
        if ($fileData['can_view_extraction_report']) {
            $report = (new FileExtractionReportResource($file))->toArray($request);
            $fileData['extraction'] = $report['extraction'];
            $fileData['failure'] = $report['failure'];
        }
        if ($entities->isEmpty()) {
            $fileData['primary_receipt'] = Receipt::withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id)->first(['id']);
            $fileData['primary_document'] = Document::withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id)->first(['id']);
        }

        $payload = [
            'file' => $fileData, 'extractedEntities' => $entities,
            'hasLegacyData' => $legacyData,
        ];

        return $request->wantsJson() ? response()->json($payload) : Inertia::render('Files/Show', $payload);
    }
}
