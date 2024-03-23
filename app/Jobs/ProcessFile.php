<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\FileService;
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

    /**
     * Create a new job instance.
     */
    protected $jobID;

    public $timeout = 3600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    public function __construct($jobID)
    {
        $this->jobID = $jobID;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $fileMetaData = Cache::get("job.{$this->jobID}.fileMetaData");
        $fileGUID = $fileMetaData['fileGUID'];
        $filePath = $fileMetaData['filePath'];

        // Proces the path given by $filePath
        $fileService = new FileService();
        $imageData = $fileService->convertPdfToImage($filePath, $fileGUID);
        File::where('guid', $fileGUID)->update(['fileImage' => base64_encode($imageData)]);

        Log::info('ProcessFile Job Completed for fileGUID: ' . $fileGUID);

    }
}
