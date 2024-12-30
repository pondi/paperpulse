<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\ConversionService;
use App\Services\DocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobID;

    public function __construct(string $jobID)
    {
        $this->jobID = $jobID;
    }

    public function handle(ConversionService $conversionService, DocumentService $documentService)
    {
        try {
            $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
            
            if (!$fileMetaData) {
                Log::error("(ProcessFile) [{$fileMetaData['jobName']}] - File metadata not found (job: {$this->jobID})", [
                    'error' => 'No file metadata found in cache'
                ]);
                return;
            }

            if ($fileMetaData['fileExtension'] === 'pdf') {
                $conversionService->pdfToImage(
                    $fileMetaData['filePath'],
                    $fileMetaData['fileGUID'],
                    $documentService
                );
            }

            Log::info("(ProcessFile) [{$fileMetaData['jobName']}] - Processing completed (file: {$fileMetaData['fileGUID']})");
        } catch (\Exception $e) {
            Log::error("(ProcessFile) [{$fileMetaData['jobName']}] - Processing failed (job: {$this->jobID})", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
