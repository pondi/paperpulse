<?php

namespace App\Services;

use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptService
{
    protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Delete a receipt and all associated resources
     */
    public function deleteReceipt(Receipt $receipt): bool
    {
        try {
            DB::beginTransaction();

            // Save references to files that should be deleted
            $fileGuid = $receipt->file?->guid;

            // Delete line items first (they reference the receipt)
            $receipt->lineItems()->delete();

            // Delete the receipt itself (this removes foreign key constraints)
            $receipt->delete();

            // Delete files from permanent storage
            if ($fileGuid) {
                $this->documentService->deleteDocument($fileGuid, 'receipts', 'pdf');
                $this->documentService->deleteDocument($fileGuid, 'receipts', 'jpg');
            }

            // Delete the file record from the database
            if ($receipt->file) {
                $receipt->file->delete();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error when deleting receipt: ' . $e->getMessage());
            return false;
        }
    }
} 