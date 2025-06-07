<?php

namespace App\Jobs;

use App\Services\ConversionService;
use Illuminate\Support\Facades\Log;

class ProcessFile extends BaseJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(string $jobID)
    {
        parent::__construct($jobID);
        $this->jobName = 'Process File';
    }

    /**
     * Execute the job's logic.
     */
    protected function handleJob(): void
    {
        try {
            $metadata = $this->getMetadata();
            if (! $metadata) {
                throw new \Exception('No metadata found for job');
            }

            Log::info('Processing file', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'file_path' => $metadata['filePath'],
            ]);

            $this->updateProgress(10);

            // Get the conversion service
            $conversionService = app(ConversionService::class);

            // Convert the file if needed
            if ($metadata['fileExtension'] !== 'pdf') {
                $pdfPath = $conversionService->convertToPdf(
                    $metadata['filePath'],
                    $metadata['fileExtension']
                );

                if (! $pdfPath) {
                    throw new \Exception('Failed to convert file to PDF');
                }

                $metadata['filePath'] = $pdfPath;
                $metadata['fileExtension'] = 'pdf';
                $this->storeMetadata($metadata);
            }

            $this->updateProgress(100);

            Log::info('File processed successfully', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
            ]);
        } catch (\Exception $e) {
            Log::error('File processing failed', [
                'job_id' => $this->jobID,
                'task_id' => $this->uuid,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
