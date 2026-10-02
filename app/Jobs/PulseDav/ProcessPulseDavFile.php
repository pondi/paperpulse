<?php

namespace App\Jobs\PulseDav;

use App\Exceptions\DuplicateFileException;
use App\Jobs\BaseJob;
use App\Models\PulseDavFile;
use App\Services\FileProcessingService;
use App\Services\PulseDav\ImportService;
use App\Services\PulseDav\ScannerImportNotifier;
use Illuminate\Support\Str;
use Throwable;

class ProcessPulseDavFile extends BaseJob
{
    public int $timeout = 3600;

    public int $tries = 5;

    public $backoff = 10;

    public function __construct(protected PulseDavFile $pulseDavFile, protected array $tagIds = [], protected ?string $note = null)
    {
        parent::__construct((string) Str::uuid());
        $this->jobName = 'Process PulseDav File';
    }

    protected function handleJob(): void
    {
        $source = PulseDavFile::query()->lockForUpdate()->findOrFail($this->pulseDavFile->id);
        if ($source->job_id !== $this->jobID) {
            return;
        }
        ImportService::reconcileFile($source);
        $source->refresh();
        if ($source->file_id !== null || $source->status === 'completed') {
            return;
        }
        $source->update(['status' => 'processing', 'claim_until' => now()->addSeconds($this->timeout + 60)]);
        try {
            $result = app(FileProcessingService::class)->processPulseDavFile($source->s3_path, $source->file_type, $source->user_id, [
                'tagIds' => $this->tagIds, 'note' => $this->note, 'pulseDavFileId' => $source->id,
                'source' => 'pulsedav', 'originalFilename' => $source->filename, 'jobId' => $this->jobID,
            ]);
            $source->update(['status' => 'handed_off', 'file_id' => $result['fileId'], 'error_message' => null]);
        } catch (DuplicateFileException $exception) {
            $source->update(['status' => 'handed_off', 'file_id' => $exception->getExistingFile()->id]);
            ImportService::reconcileFile($source);
        }
    }

    public function failed(Throwable $exception): void
    {
        parent::failed($exception);
        $source = PulseDavFile::query()->find($this->pulseDavFile->id);
        if ($source !== null && $source->job_id === $this->jobID && $source->status !== 'completed') {
            $source->markAsFailed($exception->getMessage());
            if ($source->import_batch_id !== null) {
                ScannerImportNotifier::notifyBatch($source->import_batch_id);
            }
        }
    }
}
