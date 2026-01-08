<?php

namespace App\Services\Contracts;

use App\Models\Contract;

class ContractTransformer
{
    public static function forIndex(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'contract_title' => $contract->contract_title,
            'contract_type' => $contract->contract_type,
            'parties' => $contract->parties,
            'effective_date' => $contract->effective_date?->format('Y-m-d'),
            'expiry_date' => $contract->expiry_date?->format('Y-m-d'),
            'contract_value' => $contract->contract_value,
            'currency' => $contract->currency,
            'status' => $contract->status,
            'summary' => $contract->summary,
            'governing_law' => $contract->governing_law,
            'jurisdiction' => $contract->jurisdiction,
            'file_id' => $contract->file_id,
        ];
    }

    public static function forShow(Contract $contract): array
    {
        $fileInfo = $contract->relationLoaded('file') ? self::buildFileInfo($contract) : null;

        return [
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
            'tags' => self::mapTags($contract),
            'created_at' => $contract->created_at?->toIso8601String(),
            'updated_at' => $contract->updated_at?->toIso8601String(),
        ];
    }

    private static function buildFileInfo(Contract $contract): ?array
    {
        if (! $contract->file) {
            return null;
        }

        $extension = $contract->file->fileExtension ?? 'pdf';
        $typeFolder = 'documents';
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

        $previewUrl = null;
        if ($contract->file->has_image_preview && $contract->file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $contract->file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
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

    private static function mapTags(Contract $contract): array
    {
        if (! $contract->relationLoaded('tags')) {
            return [];
        }

        return $contract->tags->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ];
        })->values()->all();
    }
}
