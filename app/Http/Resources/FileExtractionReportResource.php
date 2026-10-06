<?php

namespace App\Http\Resources;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin File */
class FileExtractionReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $warnings = data_get($this->meta, 'gemini.extraction.validation_warnings', []);
        $coverage = Arr::only($this->meta['processing_coverage'] ?? [], ['total_pages', 'processed_pages', 'complete']);

        $receipt = ($this->meta['review']['reason'] ?? null) === 'receipt_totals'
            ? $this->receipts()->where('user_id', $this->user_id)->with('lineItems')->first() : null;

        return [
            'file' => ['id' => $this->id, 'name' => $this->fileName, 'status' => $this->status, 'file_type' => $this->file_type, 'processing_provider' => $this->processing_type],
            'processing' => $this->resource->processingSummary(),
            'classification' => Arr::only(data_get($this->meta, 'gemini.classification', []), ['type', 'confidence', 'reasoning']),
            'extraction' => [
                'confidence_score' => data_get($this->meta, 'gemini.extraction.confidence_score'),
                'validation_warnings' => $warnings,
                'has_extraction_issues' => in_array($this->status, ['needs_review', 'failed'], true) || $warnings !== [] || ($coverage['complete'] ?? null) === false,
            ],
            'coverage' => $coverage,
            'review' => Arr::only($this->meta['review'] ?? [], ['reason', 'confidence', 'reasoning', 'page_limit', 'corrected_type']),
            'reconciliation' => $receipt?->totalsReconciliation(),
            'receipt_currency' => $receipt?->currency,
            'failure' => Arr::only($this->meta['gemini_error'] ?? [], ['category', 'retryable']),
            'entities' => $this->extractableEntities->map(fn ($entity): array => [
                'type' => $entity->entity_type, 'id' => $entity->entity_id, 'is_primary' => $entity->is_primary,
                'confidence_score' => $entity->confidence_score, 'provider' => $entity->extraction_provider,
                'model' => $entity->extraction_model, 'extracted_at' => $entity->extracted_at,
            ])->all(),
        ];
    }
}
