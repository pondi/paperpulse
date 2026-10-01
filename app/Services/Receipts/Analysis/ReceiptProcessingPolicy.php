<?php

namespace App\Services\Receipts\Analysis;

use App\Models\File;
use App\Models\Receipt;

class ReceiptProcessingPolicy
{
    public static function preserveUserValues(array $payload, File $file): array
    {
        $previous = Receipt::withoutGlobalScope('user')->withTrashed()->where('user_id', $file->user_id)->where('file_id', $file->id)->oldest('id')->first();
        if ($previous === null) {
            return $payload;
        }
        foreach (['category_id', 'note'] as $field) {
            if ($previous->{$field} !== null) {
                $payload[$field] = $previous->{$field};
            }
        }

        return $payload;
    }

    public static function applyReview(File $file, array $totals): void
    {
        if (! ($totals['needs_review'] ?? false)) {
            return;
        }
        $file->status = 'needs_review';
        $file->meta = array_merge($file->meta ?? [], ['review' => ['reason' => 'receipt_totals', 'reconciliation' => $totals]]);
        $file->save();
    }
}
