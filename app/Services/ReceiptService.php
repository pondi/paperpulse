<?php

namespace App\Services;

use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    protected $documentService;

    protected $receiptAnalysisService;

    protected $textExtractionService;

    public function __construct(
        DocumentService $documentService,
        ReceiptAnalysisService $receiptAnalysisService,
        TextExtractionService $textExtractionService
    ) {
        $this->documentService = $documentService;
        $this->receiptAnalysisService = $receiptAnalysisService;
        $this->textExtractionService = $textExtractionService;
    }

    /**
     * Process receipt data from a file
     */
    public function processReceiptData(int $fileId, string $fileGuid, string $filePath): array
    {
        try {
            Log::info('Processing receipt data', [
                'file_id' => $fileId,
                'file_guid' => $fileGuid,
            ]);

            // Get the file model to get user ID
            $file = \App\Models\File::findOrFail($fileId);

            // Extract text and structured data from the file using TextExtractionService
            $ocrData = $this->textExtractionService->extractWithStructuredData($filePath, 'receipt', $fileGuid);
            $ocrText = $ocrData['text'];
            $structuredData = $ocrData['structured_data'] ?? [];

            if (empty($ocrText)) {
                throw new \Exception('No text could be extracted from the file');
            }

            Log::debug('Text and structured data extracted from receipt', [
                'file_id' => $fileId,
                'text_length' => strlen($ocrText),
                'forms_count' => count($structuredData['forms'] ?? []),
                'tables_count' => count($structuredData['tables'] ?? []),
            ]);

            // Use the new ReceiptAnalysisService to analyze and create receipt
            $receipt = $this->receiptAnalysisService->analyzeAndCreateReceiptWithStructuredData(
                $ocrText,
                $structuredData,
                $fileId,
                $file->user_id
            );

            // Make the receipt searchable after all relations are saved
            $receipt->load(['merchant', 'lineItems']);
            $receipt->searchable();

            Log::info('Receipt data processed successfully', [
                'file_id' => $fileId,
                'receipt_id' => $receipt->id,
                'merchant_id' => $receipt->merchant_id,
            ]);

            return [
                'receiptId' => $receipt->id,
                'merchantName' => $receipt->merchant?->name ?? 'Unknown',
                'merchantAddress' => $receipt->merchant?->address ?? '',
                'merchantVatID' => $receipt->merchant?->vat_number ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('Receipt data processing failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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
            $fileId = $receipt->file?->id;

            // Delete line items first (they reference the receipt)
            $receipt->lineItems()->delete();

            // Delete the receipt itself
            $receipt->delete();

            // Delete files from permanent storage
            if ($fileGuid) {
                $this->documentService->deleteDocument($fileGuid, 'ReceiptService', 'receipts', 'pdf');
                $this->documentService->deleteDocument($fileGuid, 'ReceiptService', 'receipts', 'jpg');
            }

            // Delete the file record after the receipt is deleted
            // This is safe now because the receipt no longer references it
            if ($fileId) {
                $file = \App\Models\File::find($fileId);
                if ($file) {
                    $file->delete();
                }
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ReceiptService] Receipt deletion failed', [
                'error' => $e->getMessage(),
                'receipt_id' => $receipt->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
