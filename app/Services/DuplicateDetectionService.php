<?php

namespace App\Services;

use App\Models\DuplicateFlag;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Receipt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DuplicateDetectionService
{
    /**
     * Flag exact duplicates based on file hash.
     *
     * @return Collection<int, DuplicateFlag>
     */
    public function flagFileHashDuplicates(File $file): Collection
    {
        if (empty($file->file_hash)) {
            return collect();
        }

        $matches = File::where('user_id', $file->user_id)
            ->where('file_hash', $file->file_hash)
            ->where('id', '!=', $file->id)
            ->pluck('id');

        $flags = collect();

        foreach ($matches as $matchId) {
            $flag = $this->createFlag($file->user_id, $file->id, $matchId, 'hash_match');
            if ($flag) {
                $flags->push($flag);
            }
        }

        return $flags;
    }

    /**
     * Flag possible receipt duplicates using shared attributes.
     *
     * @return Collection<int, DuplicateFlag>
     */
    public function flagReceiptDuplicates(Receipt $receipt): Collection
    {
        $criteria = [
            'receipt_date' => $receipt->receipt_date?->toDateString(),
            'total_amount' => $receipt->total_amount,
            'merchant_id' => $receipt->merchant_id,
        ];

        if ($this->countNonEmpty($criteria) < 2 || empty($receipt->file_id)) {
            return collect();
        }

        $query = Receipt::where('user_id', $receipt->user_id)
            ->where('id', '!=', $receipt->id)
            ->whereNotNull('file_id');

        $query->where(function ($builder) use ($criteria) {
            if (! empty($criteria['receipt_date'])) {
                $builder->orWhereDate('receipt_date', $criteria['receipt_date']);
            }

            if (! empty($criteria['total_amount'])) {
                $builder->orWhere('total_amount', $criteria['total_amount']);
            }

            if (! empty($criteria['merchant_id'])) {
                $builder->orWhere('merchant_id', $criteria['merchant_id']);
            }
        });

        $candidates = $query->get();
        $flags = collect();

        foreach ($candidates as $candidate) {
            $matches = $this->countMatches($criteria, [
                'receipt_date' => $candidate->receipt_date?->toDateString(),
                'total_amount' => $candidate->total_amount,
                'merchant_id' => $candidate->merchant_id,
            ]);

            if ($matches < 2) {
                continue;
            }

            $reason = $this->buildReason('receipt', $criteria, [
                'receipt_date' => $candidate->receipt_date?->toDateString(),
                'total_amount' => $candidate->total_amount,
                'merchant_id' => $candidate->merchant_id,
            ]);

            $flag = $this->createFlag($receipt->user_id, $receipt->file_id, $candidate->file_id, $reason);

            if ($flag) {
                $flags->push($flag);
            }
        }

        return $flags;
    }

    /**
     * Flag possible invoice duplicates using shared attributes.
     *
     * @return Collection<int, DuplicateFlag>
     */
    public function flagInvoiceDuplicates(Invoice $invoice): Collection
    {
        $criteria = [
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'total_amount' => $invoice->total_amount,
            'invoice_number' => $invoice->invoice_number,
            'from_name' => $invoice->from_name,
        ];

        if ($this->countNonEmpty($criteria) < 2 || empty($invoice->file_id)) {
            return collect();
        }

        $query = Invoice::where('user_id', $invoice->user_id)
            ->where('id', '!=', $invoice->id)
            ->whereNotNull('file_id');

        $query->where(function ($builder) use ($criteria) {
            if (! empty($criteria['invoice_date'])) {
                $builder->orWhereDate('invoice_date', $criteria['invoice_date']);
            }

            if (! empty($criteria['total_amount'])) {
                $builder->orWhere('total_amount', $criteria['total_amount']);
            }

            if (! empty($criteria['invoice_number'])) {
                $builder->orWhere('invoice_number', $criteria['invoice_number']);
            }

            if (! empty($criteria['from_name'])) {
                $builder->orWhere('from_name', $criteria['from_name']);
            }
        });

        $candidates = $query->get();
        $flags = collect();

        foreach ($candidates as $candidate) {
            $matches = $this->countMatches($criteria, [
                'invoice_date' => $candidate->invoice_date?->toDateString(),
                'total_amount' => $candidate->total_amount,
                'invoice_number' => $candidate->invoice_number,
                'from_name' => $candidate->from_name,
            ]);

            if ($matches < 2) {
                continue;
            }

            $reason = $this->buildReason('invoice', $criteria, [
                'invoice_date' => $candidate->invoice_date?->toDateString(),
                'total_amount' => $candidate->total_amount,
                'invoice_number' => $candidate->invoice_number,
                'from_name' => $candidate->from_name,
            ]);

            $flag = $this->createFlag($invoice->user_id, $invoice->file_id, $candidate->file_id, $reason);

            if ($flag) {
                $flags->push($flag);
            }
        }

        return $flags;
    }

    protected function countMatches(array $left, array $right): int
    {
        $matches = 0;

        foreach ($left as $key => $value) {
            if ($value === null || $value === '' || $right[$key] === null || $right[$key] === '') {
                continue;
            }

            if ((string) $value === (string) $right[$key]) {
                $matches++;
            }
        }

        return $matches;
    }

    protected function buildReason(string $prefix, array $left, array $right): string
    {
        $parts = [];

        foreach ($left as $key => $value) {
            if ($value === null || $value === '' || $right[$key] === null || $right[$key] === '') {
                continue;
            }

            if ((string) $value === (string) $right[$key]) {
                $parts[] = $key;
            }
        }

        if (empty($parts)) {
            return $prefix.'_match';
        }

        return $prefix.'_match_'.implode('_', $parts);
    }

    protected function createFlag(int $userId, int $fileId, int $duplicateFileId, string $reason): ?DuplicateFlag
    {
        if (empty($fileId) || empty($duplicateFileId)) {
            return null;
        }

        if ($fileId === $duplicateFileId) {
            return null;
        }

        $pair = [$fileId, $duplicateFileId];
        sort($pair);

        [$primaryId, $secondaryId] = $pair;

        $flag = DuplicateFlag::where('user_id', $userId)
            ->where('file_id', $primaryId)
            ->where('duplicate_file_id', $secondaryId)
            ->first();

        if ($flag) {
            $updatedReason = $this->mergeReason($flag->reason, $reason);

            if ($updatedReason !== $flag->reason) {
                $flag->reason = $updatedReason;
                $flag->save();
            }

            return $flag;
        }

        $flag = DuplicateFlag::create([
            'user_id' => $userId,
            'file_id' => $primaryId,
            'duplicate_file_id' => $secondaryId,
            'reason' => $reason,
            'status' => 'open',
        ]);

        Log::info('[DuplicateDetection] Duplicate flagged', [
            'user_id' => $userId,
            'file_id' => $primaryId,
            'duplicate_file_id' => $secondaryId,
            'reason' => $reason,
        ]);

        return $flag;
    }

    protected function mergeReason(?string $existing, string $incoming): string
    {
        if (! $existing) {
            return $incoming;
        }

        $existingParts = array_filter(explode('|', $existing));
        $incomingParts = array_filter(explode('|', $incoming));
        $merged = array_unique(array_merge($existingParts, $incomingParts));

        return implode('|', $merged);
    }

    protected function countNonEmpty(array $values): int
    {
        $count = 0;

        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                $count++;
            }
        }

        return $count;
    }
}
