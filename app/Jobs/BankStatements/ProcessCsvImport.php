<?php

declare(strict_types=1);

namespace App\Jobs\BankStatements;

use App\Jobs\BaseJob;
use App\Models\File;
use App\Services\BankStatements\CsvImportService;
use App\Services\BankStatements\TransactionCategorizationService;
use App\Services\StorageService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessCsvImport extends BaseJob
{
    public int $tries = 2;

    public array $backoff = [60, 120];

    protected int $fileId;

    public function __construct(string $jobID, int $fileId)
    {
        parent::__construct($jobID);
        $this->fileId = $fileId;
        $this->jobName = 'ProcessCsvImport';
    }

    protected function handleJob(): void
    {
        $file = File::findOrFail($this->fileId);

        Log::info('[ProcessCsvImport] Starting CSV import', [
            'file_id' => $this->fileId,
            'job_id' => $this->jobID,
        ]);

        $this->updateProgress(10);

        $storageService = app(StorageService::class);
        $extension = $file->fileExtension ?? 'csv';
        $csvContent = $storageService->getFileByUserAndGuid(
            $file->user_id,
            $file->guid,
            'document',
            'original',
            $extension
        );

        if ($csvContent === null) {
            throw new RuntimeException('Could not retrieve CSV file from storage.');
        }

        $this->updateProgress(30);

        $csvImportService = app(CsvImportService::class);
        $statement = $csvImportService->importFromContent($csvContent, $file->user_id, $this->fileId);

        $this->updateProgress(60);

        $categorizationService = app(TransactionCategorizationService::class);
        $statement->load('transactions');
        $categorizationService->categorize($statement->transactions);

        $this->updateProgress(90);

        $file->update([
            'status' => 'completed',
            'processing_type' => 'bank_statement',
        ]);

        Log::info('[ProcessCsvImport] CSV import completed', [
            'file_id' => $this->fileId,
            'statement_id' => $statement->id,
            'transaction_count' => $statement->transaction_count,
        ]);

        $this->updateProgress(100);
    }

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);

        Log::error('[ProcessCsvImport] CSV import failed', [
            'file_id' => $this->fileId,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return array_merge(parent::tags(), [
            'file:'.$this->fileId,
        ]);
    }
}
