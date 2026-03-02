<?php

declare(strict_types=1);

namespace App\Http\Resources\Inertia;

use App\Http\Resources\BankTransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankStatementInertiaResource extends JsonResource
{
    protected bool $detailed = false;

    public static function forIndex($resource): self
    {
        return new self($resource);
    }

    public static function forShow($resource): self
    {
        $instance = new self($resource);
        $instance->detailed = true;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->maskAccountNumber($this->account_number),
            'statement_date' => $this->statement_date?->format('Y-m-d'),
            'statement_period_start' => $this->statement_period_start?->format('Y-m-d'),
            'statement_period_end' => $this->statement_period_end?->format('Y-m-d'),
            'opening_balance' => $this->opening_balance,
            'closing_balance' => $this->closing_balance,
            'currency' => $this->currency,
            'total_credits' => $this->total_credits,
            'total_debits' => $this->total_debits,
            'transaction_count' => $this->transaction_count,
            'file_id' => $this->file_id,
        ];

        if ($this->detailed) {
            $data = array_merge($data, [
                'account_number_full' => $this->account_number,
                'iban' => $this->iban,
                'swift_code' => $this->swift_code,
                'file' => $this->buildFileInfo(),
                'tags' => $this->mapTags(),
                'transactions' => $this->mapTransactions(),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]);
        }

        return $data;
    }

    private function maskAccountNumber(?string $accountNumber): ?string
    {
        if ($accountNumber === null || strlen($accountNumber) <= 4) {
            return $accountNumber;
        }

        return str_repeat('*', strlen($accountNumber) - 4).substr($accountNumber, -4);
    }

    private function buildFileInfo(): ?array
    {
        if (! $this->relationLoaded('file') || ! $this->file) {
            return null;
        }

        $extension = $this->file->fileExtension ?? 'pdf';
        $typeFolder = 'documents';
        $hasArchivePdf = ! empty($this->file->s3_archive_path);
        $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
        $pdfUrl = null;

        if ($hasPdfVariant) {
            $pdfUrl = route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => $typeFolder,
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($this->file->has_image_preview && $this->file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $this->file->id,
            'url' => route('documents.serve', [
                'guid' => $this->file->guid,
                'type' => $typeFolder,
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
            'extension' => $extension,
            'mime_type' => $this->file->mime_type,
            'size' => $this->file->fileSize,
            'guid' => $this->file->guid,
            'has_preview' => $this->file->has_image_preview,
            'is_pdf' => $hasPdfVariant,
            'uploaded_at' => $this->file->uploaded_at,
            'file_created_at' => $this->file->file_created_at,
            'file_modified_at' => $this->file->file_modified_at,
        ];
    }

    private function mapTags(): array
    {
        if (! $this->relationLoaded('tags')) {
            return [];
        }

        return $this->tags->map(fn ($tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ])->values()->all();
    }

    private function mapTransactions(): array
    {
        if (! $this->relationLoaded('transactions')) {
            return [];
        }

        return BankTransactionResource::collection($this->transactions)->resolve();
    }
}
