<?php

namespace App\Services\Vouchers;

use App\Models\Voucher;

class VoucherTransformer
{
    public static function forIndex(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_type' => $voucher->voucher_type,
            'code' => $voucher->code,
            'merchant' => $voucher->relationLoaded('merchant') && $voucher->merchant ? [
                'id' => $voucher->merchant->id,
                'name' => $voucher->merchant->name,
            ] : null,
            'issue_date' => $voucher->issue_date?->format('Y-m-d'),
            'expiry_date' => $voucher->expiry_date?->format('Y-m-d'),
            'original_value' => $voucher->original_value,
            'current_value' => $voucher->current_value,
            'currency' => $voucher->currency,
            'installment_count' => $voucher->installment_count,
            'monthly_payment' => $voucher->monthly_payment,
            'first_payment_date' => $voucher->first_payment_date?->format('Y-m-d'),
            'final_payment_date' => $voucher->final_payment_date?->format('Y-m-d'),
            'terms_and_conditions' => $voucher->terms_and_conditions,
            'restrictions' => $voucher->restrictions,
            'is_redeemed' => $voucher->is_redeemed,
            'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
            'file_id' => $voucher->file_id,
        ];
    }

    public static function forShow(Voucher $voucher): array
    {
        $fileInfo = $voucher->relationLoaded('file') ? self::buildFileInfo($voucher) : null;

        return [
            'id' => $voucher->id,
            'voucher_type' => $voucher->voucher_type,
            'code' => $voucher->code,
            'barcode' => $voucher->barcode,
            'qr_code' => $voucher->qr_code,
            'merchant' => $voucher->relationLoaded('merchant') && $voucher->merchant ? [
                'id' => $voucher->merchant->id,
                'name' => $voucher->merchant->name,
            ] : null,
            'issue_date' => $voucher->issue_date?->format('Y-m-d'),
            'expiry_date' => $voucher->expiry_date?->format('Y-m-d'),
            'original_value' => $voucher->original_value,
            'current_value' => $voucher->current_value,
            'currency' => $voucher->currency,
            'installment_count' => $voucher->installment_count,
            'monthly_payment' => $voucher->monthly_payment,
            'first_payment_date' => $voucher->first_payment_date?->format('Y-m-d'),
            'final_payment_date' => $voucher->final_payment_date?->format('Y-m-d'),
            'is_redeemed' => $voucher->is_redeemed,
            'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
            'redemption_location' => $voucher->redemption_location,
            'terms_and_conditions' => $voucher->terms_and_conditions,
            'restrictions' => $voucher->restrictions,
            'file_id' => $voucher->file_id,
            'file' => $fileInfo,
            'tags' => self::mapTags($voucher),
        ];
    }

    private static function buildFileInfo(Voucher $voucher): ?array
    {
        if (! $voucher->file) {
            return null;
        }

        $extension = $voucher->file->fileExtension ?? 'pdf';
        $typeFolder = 'documents';
        $hasArchivePdf = ! empty($voucher->file->s3_archive_path);
        $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
        $pdfUrl = null;

        if ($hasPdfVariant) {
            $pdfUrl = route('documents.serve', [
                'guid' => $voucher->file->guid,
                'type' => $typeFolder,
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($voucher->file->has_image_preview && $voucher->file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $voucher->file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $voucher->file->id,
            'url' => route('documents.serve', [
                'guid' => $voucher->file->guid,
                'type' => $typeFolder,
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
            'extension' => $extension,
            'mime_type' => $voucher->file->mime_type,
            'size' => $voucher->file->fileSize,
            'guid' => $voucher->file->guid,
            'has_preview' => $voucher->file->has_image_preview,
            'is_pdf' => $hasPdfVariant,
            'uploaded_at' => $voucher->file->uploaded_at?->toIso8601String(),
            'file_created_at' => $voucher->file->file_created_at?->toIso8601String(),
            'file_modified_at' => $voucher->file->file_modified_at?->toIso8601String(),
        ];
    }

    private static function mapTags(Voucher $voucher): array
    {
        if (! $voucher->relationLoaded('tags')) {
            return [];
        }

        return $voucher->tags->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ];
        })->values()->all();
    }
}
