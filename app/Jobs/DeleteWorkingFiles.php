<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteWorkingFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobID;

    /**
     * Create a new job instance.
     */
    public function __construct($jobID)
    {
        $this->jobID = $jobID;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $fileExtensions = ['.jpg', '.pdf'];

        $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
        $fileGUID = $fileMetaData['fileGUID'];

        foreach ($fileExtensions as $extension) {
            $filePath = 'uploads/' . $fileGUID . $extension;

            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            else {
                Log::error('File does not exist: ' . $filePath);
            }
        }
        Log::info('DeleteFile Job Completed - fileGUID:' . $fileGUID . ' - filePath: ' . $filePath);
    }
}
