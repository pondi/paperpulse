<?php

namespace App\Services;

use App\Models\File;
use App\Models\Receipt;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * High-level receipt processing:
 * - Extract OCR text/blocks/structured data
 * - Orchestrate analysis to create Receipt model + relations
 * - Persist artifacts (OCR and AI outputs) to long-term storage
 * - Support safe deletion of receipts and related files
 */
class ReceiptService
{
    protected $documentService;

    protected $receiptAnalysisService;

    protected $textExtractionService;

    protected StorageService $storageService;

    public function __construct(
        DocumentService $documentService,
        ReceiptAnalysisService $receiptAnalysisService,
        TextExtractionService $textExtractionService,
        StorageService $storageService
    ) {
        $this->documentService = $documentService;
        $this->receiptAnalysisService = $receiptAnalysisService;
        $this->textExtractionService = $textExtractionService;
        $this->storageService = $storageService;
    }

    /**
     * Process receipt data from a file.
     *
     * @param  string  $filePath  Absolute working path
     * @return array{receiptId:int,merchantName:string,merchantAddress:string,merchantVatID:string}
     */
    public function processReceiptData(int $fileId, string $fileGuid, string $filePath, ?string $note = null, bool $isReprocessing = false): array
    {
        try {
            Log::info('Processing receipt data', [
                'file_id' => $fileId,
                'file_guid' => $fileGuid,
            ]);

            // Get the file model to get user ID
            $file = File::findOrFail($fileId);

            // Check if we're reprocessing - delete existing receipt if so
            if ($isReprocessing) {
                $existingReceipt = Receipt::where('file_id', $fileId)->first();
                if ($existingReceipt) {
                    Log::info('[ReceiptService] Deleting existing receipt during reprocessing', [
                        'file_id' => $fileId,
                        'receipt_id' => $existingReceipt->id,
                        'line_items_count' => $existingReceipt->lineItems()->count(),
                    ]);

                    // Delete line items first
                    $existingReceipt->lineItems()->delete();

                    // Delete the receipt
                    $existingReceipt->delete();

                    Log::info('[ReceiptService] Existing receipt deleted successfully', [
                        'file_id' => $fileId,
                    ]);
                }
            }

            // Extract text and structured data from the file using TextExtractionService
            $ocrData = $this->textExtractionService->extractWithStructuredData($filePath, 'receipt', $fileGuid);
            $ocrText = $ocrData['text'];
            $structuredData = $ocrData['structured_data'] ?? [];

            if (empty($ocrText)) {
                throw new Exception('No text could be extracted from the file');
            }

            Log::debug('Text and structured data extracted from receipt', [
                'file_id' => $fileId,
                'text_length' => strlen($ocrText),
                'forms_count' => count($structuredData['forms'] ?? []),
                'tables_count' => count($structuredData['tables'] ?? []),
            ]);

            // Persist OCR artifacts (text/blocks/structured) for auditing and retraining
            $this->persistOcrArtifacts($file->user_id, $fileGuid, $ocrText, $structuredData, $ocrData['blocks'] ?? [], $ocrData['ocr_metadata'] ?? []);

            // Use the new ReceiptAnalysisService to analyze and create receipt
            $receipt = $this->receiptAnalysisService->analyzeAndCreateReceiptWithStructuredData(
                $ocrText,
                $structuredData,
                $fileId,
                $file->user_id,
                $note
            );

            // Make the receipt searchable after all relations are saved
            $receipt->load(['merchant', 'lineItems']);
            $receipt->searchable();

            // Persist AI response (as stored in receipt_data) for auditing
            try {
                // Convert array to JSON string if needed (receipt_data is cast as array in model)
                $receiptDataJson = is_array($receipt->receipt_data)
                    ? json_encode($receipt->receipt_data)
                    : $receipt->receipt_data;
                $this->persistAiArtifacts($file->user_id, $fileGuid, $receiptDataJson);
            } catch (Exception $e) {
                Log::warning('[ReceiptService] Failed to persist AI artifacts', [
                    'file_id' => $fileId,
                    'file_guid' => $fileGuid,
                    'error' => $e->getMessage(),
                ]);
            }

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

        } catch (Exception $e) {
            Log::error('Receipt data processing failed', [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a receipt and all associated resources.
     *
     * @return bool True if deletion succeeded
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
                $file = File::find($fileId);
                if ($file) {
                    $file->delete();
                }
            }

            DB::commit();

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[ReceiptService] Receipt deletion failed', [
                'error' => $e->getMessage(),
                'receipt_id' => $receipt->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Persist OCR artifacts (text, structured data, blocks, metadata) to long-term storage.
     */
    protected function persistOcrArtifacts(int $userId, string $fileGuid, string $text, array $structuredData, array $blocks, array $ocrMetadata): void
    {
        try {
            $paths = [];
            $prettyStructured = (bool) config('ai.ocr.options.pretty_print_structured', false);
            $storeBlocks = (bool) config('ai.ocr.options.store_blocks', false);
            $prettyBlocks = (bool) config('ai.ocr.options.pretty_print_blocks', false);

            // Store plain OCR text
            if (! empty($text)) {
                $paths['ocr_text'] = $this->storageService->storeFile($text, $userId, $fileGuid, 'receipt', 'ocr_text', 'txt');
            }

            // Store structured OCR data
            $structuredFlags = $prettyStructured ? JSON_PRETTY_PRINT : 0;
            $structuredJson = json_encode($structuredData, $structuredFlags);
            if ($structuredJson !== false) {
                $paths['ocr_structured'] = $this->storageService->storeFile($structuredJson, $userId, $fileGuid, 'receipt', 'ocr_structured', 'json');
            }

            // Store raw blocks (closest to original Textract response content)
            if ($storeBlocks && ! empty($blocks)) {
                $blockFlags = $prettyBlocks ? JSON_PRETTY_PRINT : 0;
                $blocksJson = json_encode($blocks, $blockFlags);
                if ($blocksJson !== false) {
                    $paths['ocr_blocks'] = $this->storageService->storeFile($blocksJson, $userId, $fileGuid, 'receipt', 'ocr_blocks', 'json');
                }
            }

            // Store OCR metadata if present (e.g., counts, job_id)
            if (! empty($ocrMetadata)) {
                $metaJson = json_encode($ocrMetadata, JSON_PRETTY_PRINT);
                if ($metaJson !== false) {
                    $paths['ocr_meta'] = $this->storageService->storeFile($metaJson, $userId, $fileGuid, 'receipt', 'ocr_meta', 'json');
                }
            }

            // Update File.meta with artifact references
            $file = File::where('guid', $fileGuid)->first();
            if ($file) {
                $meta = $file->meta ?? [];
                $meta['artifacts'] = array_merge($meta['artifacts'] ?? [], $paths);
                $file->meta = $meta;
                $file->save();
            }
        } catch (Exception $e) {
            Log::warning('[ReceiptService] Persist OCR artifacts failed', [
                'file_guid' => $fileGuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist AI analysis response (as JSON string) to long-term storage and reference on File.meta.
     */
    protected function persistAiArtifacts(int $userId, string $fileGuid, ?string $receiptDataJson): void
    {
        if (empty($receiptDataJson)) {
            return;
        }

        $path = $this->storageService->storeFile($receiptDataJson, $userId, $fileGuid, 'receipt', 'ai_response', 'json');

        $file = File::where('guid', $fileGuid)->first();
        if ($file) {
            $meta = $file->meta ?? [];
            $meta['artifacts'] = array_merge($meta['artifacts'] ?? [], [
                'ai_response' => $path,
                'ai_response_persisted' => true,
            ]);
            $file->meta = $meta;
            $file->save();
        }
    }
}
