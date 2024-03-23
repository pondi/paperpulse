<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{

    public function index()
    {
        $receipts = Receipt::all();
        return view('receipts', compact('receipts'));
    }

    public function showLineItems(Receipt $receipt)
    {
        if ($receipt && $receipt->lineItems) {
            $lineItems = $receipt->lineItems;
            return view('lineitems', compact('lineItems'));
        } else {
            return view('receipts', compact('receipts'));
        }
    }

    public function showFile(Receipt $receipt)
    {
        $filePath = 'receipts/' . $receipt->file->guid . '.' . $receipt->file->fileExtention;
        $stream = Storage::disk('textract')->readStream($filePath);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => $receipt->file->fileType,
            'Content-Disposition' => 'inline; filename="' . $receipt->file->name . '.' . $receipt->file->fileExtention . '"',
        ]);
    }

    public function showDetails(Receipt $receipt)
    {
        return view('receiptdetail', compact('receipt'));
    }

    public function showMerchant($merchant)
    {
        $receipts = Receipt::where('merchant_id', $merchant)->get();
        return view('receipts', compact('receipts'));
    }
}
