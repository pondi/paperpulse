<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

class InvoiceTransformer
{
    public static function forIndex(Invoice $invoice): array
    {
        $lineItemsCount = $invoice->line_items_count ?? ($invoice->relationLoaded('lineItems') ? $invoice->lineItems->count() : 0);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_type' => $invoice->invoice_type,
            'from_name' => $invoice->from_name,
            'to_name' => $invoice->to_name,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'total_amount' => $invoice->total_amount,
            'amount_due' => $invoice->amount_due,
            'currency' => $invoice->currency,
            'payment_status' => $invoice->payment_status,
            'payment_terms' => $invoice->payment_terms,
            'line_items_count' => $lineItemsCount,
            'file_id' => $invoice->file_id,
        ];
    }

    public static function forShow(Invoice $invoice): array
    {
        $fileInfo = $invoice->relationLoaded('file') ? self::buildFileInfo($invoice) : null;

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_type' => $invoice->invoice_type,
            'from_name' => $invoice->from_name,
            'from_address' => $invoice->from_address,
            'from_vat_number' => $invoice->from_vat_number,
            'from_email' => $invoice->from_email,
            'from_phone' => $invoice->from_phone,
            'to_name' => $invoice->to_name,
            'to_address' => $invoice->to_address,
            'to_vat_number' => $invoice->to_vat_number,
            'to_email' => $invoice->to_email,
            'to_phone' => $invoice->to_phone,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'delivery_date' => $invoice->delivery_date?->format('Y-m-d'),
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'discount_amount' => $invoice->discount_amount,
            'shipping_amount' => $invoice->shipping_amount,
            'total_amount' => $invoice->total_amount,
            'amount_paid' => $invoice->amount_paid,
            'amount_due' => $invoice->amount_due,
            'currency' => $invoice->currency,
            'payment_method' => $invoice->payment_method,
            'payment_status' => $invoice->payment_status,
            'payment_terms' => $invoice->payment_terms,
            'purchase_order_number' => $invoice->purchase_order_number,
            'reference_number' => $invoice->reference_number,
            'notes' => $invoice->notes,
            'line_items' => self::mapLineItems($invoice),
            'file_id' => $invoice->file_id,
            'file' => $fileInfo,
            'tags' => self::mapTags($invoice),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    private static function buildFileInfo(Invoice $invoice): ?array
    {
        if (! $invoice->file) {
            return null;
        }

        $extension = $invoice->file->fileExtension ?? 'pdf';
        $typeFolder = 'documents';
        $hasArchivePdf = ! empty($invoice->file->s3_archive_path);
        $hasPdfVariant = $hasArchivePdf || strtolower($extension) === 'pdf';
        $pdfUrl = null;

        if ($hasPdfVariant) {
            $pdfUrl = route('documents.serve', [
                'guid' => $invoice->file->guid,
                'type' => $typeFolder,
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($invoice->file->has_image_preview && $invoice->file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $invoice->file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $invoice->file->id,
            'url' => route('documents.serve', [
                'guid' => $invoice->file->guid,
                'type' => $typeFolder,
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
            'extension' => $extension,
            'mime_type' => $invoice->file->mime_type,
            'size' => $invoice->file->fileSize,
            'guid' => $invoice->file->guid,
            'has_preview' => $invoice->file->has_image_preview,
            'is_pdf' => $hasPdfVariant,
            'uploaded_at' => $invoice->file->uploaded_at?->toIso8601String(),
            'file_created_at' => $invoice->file->file_created_at?->toIso8601String(),
            'file_modified_at' => $invoice->file->file_modified_at?->toIso8601String(),
        ];
    }

    private static function mapLineItems(Invoice $invoice): array
    {
        if (! $invoice->relationLoaded('lineItems')) {
            return [];
        }

        return $invoice->lineItems->map(function ($item) {
            return [
                'id' => $item->id,
                'line_number' => $item->line_number,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'total_amount' => $item->total_amount,
            ];
        })->values()->all();
    }

    private static function mapTags(Invoice $invoice): array
    {
        if (! $invoice->relationLoaded('tags')) {
            return [];
        }

        return $invoice->tags->map(function ($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ];
        })->values()->all();
    }
}
