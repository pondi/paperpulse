<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Receipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Transforms raw search results into the standardized response format.
 */
class SearchResultFormatter
{
    public function formatReceipts(Collection $results): Collection
    {
        return $results->map(function ($receipt) {
            $metadata = method_exists($receipt, 'scoutMetadata') ? $receipt->scoutMetadata() : [];
            $description = $receipt->note ?: $receipt->summary ?: $this->buildReceiptDescription($receipt);

            return [
                'id' => $receipt->id,
                'file_id' => $receipt->file_id,
                'type' => 'receipt',
                'title' => $receipt->merchant?->name ?? 'Unknown Merchant',
                'description' => $description,
                'filename' => $receipt->file?->original_filename ?? $receipt->file?->fileName,
                'url' => route('receipts.show', $receipt->id),
                'date' => $this->formatDate($receipt->receipt_date),
                'total' => $receipt->total_amount ? number_format((float) $receipt->total_amount, 2).' '.$receipt->currency : null,
                'category' => $receipt->receipt_category,
                'tags' => $receipt->tags ? $receipt->tags->pluck('name')->all() : [],
                'items' => $receipt->lineItems ? $receipt->lineItems->take(3)->map(function ($item) use ($receipt) {
                    return [
                        'description' => $item->text ?? '',
                        'quantity' => $item->qty ?? 0,
                        'price' => number_format((float) ($item->price ?? 0), 2).' '.($receipt->currency ?? ''),
                    ];
                })->all() : [],
                'file' => $receipt->file ? [
                    'id' => $receipt->file->id,
                    'guid' => $receipt->file->guid,
                    'filename' => $receipt->file->original_filename ?? $receipt->file->fileName,
                    'extension' => $receipt->file->fileExtension,
                    'has_image_preview' => (bool) $receipt->file->has_image_preview,
                    'has_archive_pdf' => ! empty($receipt->file->s3_archive_path),
                    'url' => route('receipts.showImage', $receipt->id),
                    'pdfUrl' => $receipt->file->guid && $receipt->file->fileExtension === 'pdf' ? route('receipts.showPdf', $receipt->id) : null,
                    'previewUrl' => $receipt->file->has_image_preview ? route('receipts.showImage', $receipt->id) : null,
                ] : null,
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatDocuments(Collection $results): Collection
    {
        return $results->map(function ($document) {
            $metadata = method_exists($document, 'scoutMetadata') ? $document->scoutMetadata() : [];

            $description = $document->note ?: $document->summary ?: $document->description;
            if (! $description) {
                $text = $document->extracted_text;
                if (is_array($text)) {
                    $text = implode(' ', $text);
                }
                if (is_string($text)) {
                    $description = $this->truncateText($text, 150);
                }
            }

            return [
                'id' => $document->id,
                'file_id' => $document->file_id,
                'type' => 'document',
                'title' => $document->title ?? 'Untitled Document',
                'description' => $description,
                'filename' => $document->file?->original_filename ?? $document->file?->fileName,
                'url' => route('documents.show', $document->id),
                'date' => $this->formatDate($document->document_date ?? $document->created_at),
                'document_type' => $document->document_type,
                'category' => $document->category?->name ?? null,
                'tags' => $document->tags ? $document->tags->pluck('name')->all() : [],
                'entities' => $document->entities ?? [],
                'file' => $this->buildEntityFileInfo($document->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatInvoices(Collection $results): Collection
    {
        return $results->map(function ($invoice) {
            $metadata = method_exists($invoice, 'scoutMetadata') ? $invoice->scoutMetadata() : [];
            $title = $invoice->from_name ?: ('Invoice #'.$invoice->invoice_number);
            $description = $invoice->notes ?: ($invoice->to_name ? 'To: '.$invoice->to_name : null);

            return [
                'id' => $invoice->id,
                'file_id' => $invoice->file?->id,
                'type' => 'invoice',
                'title' => $title,
                'description' => $description,
                'filename' => $invoice->file?->original_filename ?? $invoice->file?->fileName,
                'url' => route('invoices.show', $invoice->id),
                'date' => $this->formatDate($invoice->invoice_date),
                'total' => $invoice->total_amount ? number_format((float) $invoice->total_amount, 2).' '.($invoice->currency ?? '') : null,
                'payment_status' => $invoice->payment_status,
                'invoice_number' => $invoice->invoice_number,
                'tags' => $invoice->tags ? $invoice->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($invoice->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatContracts(Collection $results): Collection
    {
        return $results->map(function ($contract) {
            $metadata = method_exists($contract, 'scoutMetadata') ? $contract->scoutMetadata() : [];

            return [
                'id' => $contract->id,
                'file_id' => $contract->file?->id,
                'type' => 'contract',
                'title' => $contract->contract_title ?: ('Contract #'.$contract->contract_number),
                'description' => $contract->summary,
                'filename' => $contract->file?->original_filename ?? $contract->file?->fileName,
                'url' => route('contracts.show', $contract->id),
                'date' => $this->formatDate($contract->effective_date),
                'contract_type' => $contract->contract_type,
                'status' => $contract->status,
                'total' => $contract->contract_value ? number_format((float) $contract->contract_value, 2).' '.($contract->currency ?? '') : null,
                'tags' => $contract->tags ? $contract->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($contract->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatVouchers(Collection $results): Collection
    {
        return $results->map(function ($voucher) {
            $metadata = method_exists($voucher, 'scoutMetadata') ? $voucher->scoutMetadata() : [];
            $title = $voucher->code ?: ($voucher->merchant?->name ? $voucher->merchant->name.' Voucher' : 'Voucher');

            return [
                'id' => $voucher->id,
                'file_id' => $voucher->file?->id,
                'type' => 'voucher',
                'title' => $title,
                'description' => $voucher->terms_and_conditions ? $this->truncateText($voucher->terms_and_conditions, 150) : null,
                'filename' => $voucher->file?->original_filename ?? $voucher->file?->fileName,
                'url' => route('vouchers.show', $voucher->id),
                'date' => $this->formatDate($voucher->expiry_date),
                'total' => $voucher->current_value ? number_format((float) $voucher->current_value, 2).' '.($voucher->currency ?? '') : null,
                'voucher_type' => $voucher->voucher_type,
                'is_redeemed' => $voucher->is_redeemed,
                'tags' => $voucher->tags ? $voucher->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($voucher->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatWarranties(Collection $results): Collection
    {
        return $results->map(function ($warranty) {
            $metadata = method_exists($warranty, 'scoutMetadata') ? $warranty->scoutMetadata() : [];

            return [
                'id' => $warranty->id,
                'file_id' => $warranty->file?->id,
                'type' => 'warranty',
                'title' => $warranty->product_name ?: 'Warranty',
                'description' => $warranty->coverage_description ? $this->truncateText($warranty->coverage_description, 150) : ($warranty->manufacturer ? 'Manufacturer: '.$warranty->manufacturer : null),
                'filename' => $warranty->file?->original_filename ?? $warranty->file?->fileName,
                'url' => $warranty->file ? route('documents.show', $warranty->file->id) : null,
                'date' => $this->formatDate($warranty->warranty_end_date),
                'warranty_type' => $warranty->warranty_type,
                'manufacturer' => $warranty->manufacturer,
                'tags' => $warranty->tags ? $warranty->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($warranty->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatReturnPolicies(Collection $results): Collection
    {
        return $results->map(function ($policy) {
            $metadata = method_exists($policy, 'scoutMetadata') ? $policy->scoutMetadata() : [];
            $title = $policy->merchant?->name ? $policy->merchant->name.' Return Policy' : 'Return Policy';

            return [
                'id' => $policy->id,
                'file_id' => $policy->file?->id,
                'type' => 'return_policy',
                'title' => $title,
                'description' => $policy->conditions ? $this->truncateText($policy->conditions, 150) : ($policy->refund_method ? 'Refund: '.$policy->refund_method : null),
                'filename' => $policy->file?->original_filename ?? $policy->file?->fileName,
                'url' => $policy->file ? route('documents.show', $policy->file->id) : null,
                'date' => $this->formatDate($policy->return_deadline),
                'is_final_sale' => $policy->is_final_sale,
                'refund_method' => $policy->refund_method,
                'tags' => $policy->tags ? $policy->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($policy->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function formatBankStatements(Collection $results): Collection
    {
        return $results->map(function ($statement) {
            $metadata = method_exists($statement, 'scoutMetadata') ? $statement->scoutMetadata() : [];
            $title = $statement->bank_name ?: 'Bank Statement';
            $description = $statement->account_holder_name ? 'Account: '.$statement->account_holder_name : null;

            return [
                'id' => $statement->id,
                'file_id' => $statement->file?->id,
                'type' => 'bank_statement',
                'title' => $title,
                'description' => $description,
                'filename' => $statement->file?->original_filename ?? $statement->file?->fileName,
                'url' => $statement->file ? route('documents.show', $statement->file->id) : null,
                'date' => $this->formatDate($statement->statement_date),
                'total' => $statement->closing_balance ? number_format((float) $statement->closing_balance, 2).' '.($statement->currency ?? '') : null,
                'transaction_count' => $statement->transaction_count,
                'tags' => $statement->tags ? $statement->tags->pluck('name')->all() : [],
                'file' => $this->buildEntityFileInfo($statement->file),
                '_rankingScore' => $metadata['_rankingScore'] ?? null,
            ];
        });
    }

    public function buildEntityFileInfo($file): ?array
    {
        if (! $file || ! $file->guid) {
            return null;
        }

        $extension = $file->fileExtension ?? 'pdf';
        $hasArchivePdf = ! empty($file->s3_archive_path);

        $pdfUrl = null;
        if ($hasArchivePdf || strtolower($extension) === 'pdf') {
            $pdfUrl = route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'documents',
                'extension' => 'pdf',
                'variant' => $hasArchivePdf ? 'archive' : 'original',
            ]);
        }

        $previewUrl = null;
        if ($file->has_image_preview && $file->s3_image_path) {
            $previewUrl = route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'preview',
                'extension' => 'jpg',
            ]);
        }

        return [
            'id' => $file->id,
            'guid' => $file->guid,
            'filename' => $file->original_filename ?? $file->fileName,
            'extension' => $extension,
            'has_image_preview' => (bool) $file->has_image_preview,
            'has_archive_pdf' => $hasArchivePdf,
            'url' => route('documents.serve', [
                'guid' => $file->guid,
                'type' => 'documents',
                'extension' => $extension,
            ]),
            'pdfUrl' => $pdfUrl,
            'previewUrl' => $previewUrl,
        ];
    }

    protected function buildReceiptDescription(Receipt $receipt): string
    {
        $parts = [];

        if ($receipt->merchant) {
            $parts[] = $receipt->merchant->name;
        }
        if ($receipt->total_amount) {
            $parts[] = number_format((float) $receipt->total_amount, 2).' '.$receipt->currency;
        }
        if ($receipt->receipt_date) {
            $parts[] = $this->formatDate($receipt->receipt_date);
        }
        if ($receipt->receipt_category) {
            $parts[] = $receipt->receipt_category;
        }

        return implode(' - ', $parts);
    }

    public function formatDate($date): ?string
    {
        if (! $date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    public function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3).'...';
    }
}
