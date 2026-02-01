<?php

namespace App\Http\Resources\Inertia;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuplicateFlagInertiaResource extends JsonResource
{
    public static function forIndex($resource): self
    {
        return new self($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'resolved_at' => $this->resolved_at,
            'file' => $this->transformFile($this->file),
            'duplicate_file' => $this->transformFile($this->duplicateFile),
        ];
    }

    protected function transformFile(?File $file): ?array
    {
        if (! $file) {
            return null;
        }

        $base = FileInertiaResource::forIndex($file)->withDetailsUrl()->toArray(request());

        $base['summary'] = $this->buildSummary($file);

        return $base;
    }

    protected function buildSummary(File $file): ?array
    {
        $primaryEntity = $file->primaryEntity;
        $entity = $primaryEntity?->entity;

        if (! $entity) {
            return null;
        }

        $entityType = $primaryEntity->entity_type;

        return match ($entityType) {
            'receipt' => [
                'type' => 'receipt',
                'date' => $entity->receipt_date?->toDateString(),
                'total_amount' => $entity->total_amount,
                'currency' => $entity->currency,
                'merchant_name' => $entity->merchant?->name,
            ],
            'document' => [
                'type' => 'document',
                'title' => $entity->title,
                'document_type' => $entity->document_type,
            ],
            'contract' => [
                'type' => 'contract',
                'title' => $entity->contract_title ?? $entity->title,
            ],
            'invoice' => [
                'type' => 'invoice',
                'vendor_name' => $entity->vendor_name ?? $entity->from_name,
                'total_amount' => $entity->total_amount,
                'currency' => $entity->currency,
            ],
            default => [
                'type' => $entityType,
            ],
        };
    }
}
