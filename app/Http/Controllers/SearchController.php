<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');
        
        if (empty($query)) {
            return response()->json(['results' => []]);
        }

        $results = Receipt::search($query)
            ->query(function ($builder) {
                $builder->with(['merchant', 'lineItems']);
            })
            ->get()
            ->map(function ($receipt) {
                $description = '';
                
                // Add merchant info if available
                if ($receipt->merchant) {
                    $description .= $receipt->merchant->name;
                }
                
                // Add total amount
                if ($receipt->total_amount) {
                    $description .= ' - ' . number_format($receipt->total_amount, 2) . ' ' . $receipt->currency;
                }
                
                // Add date if available
                if ($receipt->receipt_date) {
                    $date = $receipt->receipt_date instanceof Carbon 
                        ? $receipt->receipt_date 
                        : Carbon::parse($receipt->receipt_date);
                    $description .= ' - ' . $date->format('Y-m-d');
                }
                
                // Add receipt category if available
                if ($receipt->receipt_category) {
                    $description .= ' - ' . $receipt->receipt_category;
                }

                return [
                    'id' => $receipt->id,
                    'title' => $receipt->merchant?->name ?? 'Unknown Merchant',
                    'description' => $description,
                    'url' => route('receipts.show', $receipt->id),
                    'date' => $receipt->receipt_date ? (
                        $receipt->receipt_date instanceof Carbon 
                            ? $receipt->receipt_date->format('Y-m-d')
                            : Carbon::parse($receipt->receipt_date)->format('Y-m-d')
                    ) : null,
                    'total' => $receipt->total_amount ? number_format($receipt->total_amount, 2) . ' ' . $receipt->currency : null,
                    'category' => $receipt->receipt_category,
                    'items' => $receipt->lineItems->take(3)->map(function ($item) {
                        return $item->text;
                    })->join(', '),
                ];
            });

        return response()->json(['results' => $results]);
    }
}
