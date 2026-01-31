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
        $receipt = $file->primaryReceipt;
        if ($receipt) {
            return [
                'type' => 'receipt',
                'date' => $receipt->receipt_date?->toDateString(),
                'total_amount' => $receipt->total_amount,
                'currency' => $receipt->currency,
                'merchant_name' => $receipt->merchant?->name,
            ];
        }

        $document = $file->primaryDocument;
        if ($document) {
            return [
                'type' => 'document',
                'title' => $document->title,
                'document_type' => $document->document_type,
            ];
        }

        return null;
    }
}
