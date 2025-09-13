<?php

namespace App\Services\Receipts;

class ReceiptSortApplier
{
    public static function apply($query, string $sortOption): void
    {
        switch ($sortOption) {
            case 'date_asc':
                $query->orderBy('receipt_date', 'asc');
                return;
            case 'amount_desc':
                $query->orderBy('total_amount', 'desc');
                return;
            case 'amount_asc':
                $query->orderBy('total_amount', 'asc');
                return;
            case 'merchant_asc':
                $query->leftJoin('merchants', 'receipts.merchant_id', '=', 'merchants.id')
                    ->orderBy('merchants.name', 'asc')
                    ->select('receipts.*');
                return;
            case 'merchant_desc':
                $query->leftJoin('merchants', 'receipts.merchant_id', '=', 'merchants.id')
                    ->orderBy('merchants.name', 'desc')
                    ->select('receipts.*');
                return;
            case 'date_desc':
            default:
                $query->orderBy('receipt_date', 'desc');
        }
    }
}

