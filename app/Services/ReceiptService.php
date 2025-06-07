<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\LineItem;
use HelgeSverre\ReceiptScanner\ReceiptScanner;
use HelgeSverre\ReceiptScanner\Facades\Text;
use HelgeSverre\ReceiptScanner\ModelNames;
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
     * Process receipt data from a file
     */
    public function processReceiptData(int $fileId, string $fileGuid, string $filePath): array
    {
        try {
            Log::info("Processing receipt data", [
                'file_id' => $fileId,
                'file_guid' => $fileGuid
            ]);

            // Get the file content
            $fileContent = file_get_contents($filePath);
            if (!$fileContent) {
                throw new \Exception('Could not read file content');
            }

            // Extract text from the file using Textract
            $textPdfOcr = Text::textractUsingS3Upload($fileContent);

            // Parse the receipt using GPT
            $scanner = new ReceiptScanner();
            $parsedReceipt = $scanner->scan(
                text: $textPdfOcr,
                model: ModelNames::TURBO_INSTRUCT,
                maxTokens: 500,
                temperature: 0.2,
                template: 'norwegian-receipt',
                asArray: true,
            );

            Log::debug("Receipt parsed", [
                'file_id' => $fileId,
                'parsed_data' => $parsedReceipt
            ]);

            // Create the receipt record
            DB::beginTransaction();

            $receipt = new Receipt;
            $receipt->file_id = $fileId;
            $receipt->receipt_data = json_encode($parsedReceipt);
            $receipt->receipt_date = $parsedReceipt['date'] ?? null;
            $receipt->tax_amount = $parsedReceipt['taxAmount'] ?? null;
            $receipt->total_amount = $parsedReceipt['totalAmount'] ?? null;
            $receipt->currency = $parsedReceipt['currency'] ?? null;
            $receipt->receipt_category = $parsedReceipt['category'] ?? null;
            $receipt->receipt_description = $parsedReceipt['description'] ?? null;
            $receipt->save();

            // Create line items
            foreach ($parsedReceipt['lineItems'] as $item) {
                $lineItem = new LineItem;
                $lineItem->receipt_id = $receipt->id;
                $lineItem->text = $item['text'];
                $lineItem->sku = $item['sku'];
                $lineItem->qty = $item['qty'];
                $lineItem->price = $item['price'];
                $lineItem->save();
            }

            DB::commit();

            // Make the receipt searchable after all relations are saved
            $receipt->load(['merchant', 'lineItems']);
            $receipt->searchable();

            Log::info("Receipt data processed successfully", [
                'file_id' => $fileId,
                'receipt_id' => $receipt->id
            ]);

            return [
                'receiptID' => $receipt->id,
                'merchantName' => $parsedReceipt['merchant']['name'],
                'merchantAddress' => $parsedReceipt['merchant']['address'],
                'merchantVatID' => $parsedReceipt['merchant']['vatId'],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Receipt data processing failed", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'trace' => $e->getTraceAsString()
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
            Log::error('[ReceiptService] Receipt deletion failed', [
                'error' => $e->getMessage(),
                'receipt_id' => $receipt->id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
} 