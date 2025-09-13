<?php

namespace App\Services\Files;

use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\PulseDav\UpdatePulseDavFileStatus;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Jobs\System\ApplyTags;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FileJobChainDispatcher
{
    public function dispatch(string $jobId, string $fileType): void
    {
        $metadata = Cache::get("job.{$jobId}.fileMetaData");
        $source = $metadata['metadata']['source'] ?? 'upload';
        $tagIds = $metadata['metadata']['tagIds'] ?? [];
        $pulseDavFileId = $metadata['metadata']['pulseDavFileId'] ?? null;

        Log::info('Dispatching job chain', [
            'jobId' => $jobId,
            'fileType' => $fileType,
            'source' => $source,
            'tagIds' => $tagIds,
            'pulseDavFileId' => $pulseDavFileId,
            'jobName' => $metadata['jobName'] ?? 'Unknown',
        ]);

        $queue = $fileType === 'receipt' ? 'receipts' : 'documents';
        $jobs = [];

        if ($fileType === 'receipt') {
            $jobs = [
                (new ProcessFile($jobId))->onQueue($queue),
                (new ProcessReceipt($jobId))->onQueue($queue),
                (new MatchMerchant($jobId))->onQueue($queue),
            ];
        } else {
            $jobs = [
                (new ProcessFile($jobId))->onQueue($queue),
                (new ProcessDocument($jobId))->onQueue($queue),
                (new AnalyzeDocument($jobId))->onQueue($queue),
            ];
        }

        if (! empty($tagIds) && isset($metadata['fileId'])) {
            $file = \App\Models\File::find($metadata['fileId']);
            if ($file) {
                $jobs[] = (new ApplyTags($jobId, $file, $tagIds))->onQueue($queue);
            }
        }

        $jobs[] = (new DeleteWorkingFiles($jobId))->onQueue($queue);

        if ($source === 'pulsedav' && $pulseDavFileId && isset($metadata['fileId'])) {
            $file = \App\Models\File::find($metadata['fileId']);
            if ($file) {
                $jobs[] = (new UpdatePulseDavFileStatus($jobId, $file, $pulseDavFileId, $fileType))->onQueue($queue);
            }
        }

        Bus::chain($jobs)->dispatch();
    }
}

