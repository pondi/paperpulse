<?php

namespace App\Services\Duplicates;

use App\Models\DuplicateFlag;
use App\Models\File;
use App\Services\Files\FileTransformer;

class DuplicateFlagTransformer
{
    public static function forIndex(DuplicateFlag $flag): array
    {
        return [
            'id' => $flag->id,
            'reason' => $flag->reason,
            'status' => $flag->status,
            'created_at' => $flag->created_at?->toIso8601String(),
            'resolved_at' => $flag->resolved_at?->toIso8601String(),
            'file' => self::transformFile($flag->file),
            'duplicate_file' => self::transformFile($flag->duplicateFile),
        ];
    }

    protected static function transformFile(?File $file): ?array
    {
        if (! $file) {
            return null;
        }

        $base = FileTransformer::forIndex($file);

        $base['detailsUrl'] = route('files.show', $file->id);
        $base['summary'] = self::buildSummary($file);

        return $base;
    }

    protected static function buildSummary(File $file): ?array
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

        $invoice = $file->invoices->first();
        if ($invoice) {
            return [
                'type' => 'invoice',
                'date' => $invoice->invoice_date?->toDateString(),
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'invoice_number' => $invoice->invoice_number,
                'from_name' => $invoice->from_name,
            ];
        }

        return null;
    }
}
