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
                Log::error('ProcessFile - No file metadata found in cache', [
                    'jobID' => $this->jobID
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

            Log::info('ProcessFile - Complete', [
                'jobID' => $this->jobID,
                'fileMetaData' => $fileMetaData
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessFile - Error: ' . $e->getMessage(), [
                'jobID' => $this->jobID,
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
