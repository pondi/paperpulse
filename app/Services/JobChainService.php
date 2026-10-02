<?php

namespace App\Services;

use App\Jobs\BankStatements\ProcessCsvImport;
use App\Jobs\BaseJob;
use App\Jobs\Documents\AnalyzeDocument;
use App\Jobs\Documents\ProcessDocument;
use App\Jobs\Files\ConvertOfficeFile;
use App\Jobs\Files\ProcessFile;
use App\Jobs\Files\ProcessFileGemini;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\PulseDav\UpdatePulseDavFileStatus;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Jobs\System\ApplyTags;
use App\Models\File;
use App\Models\FileConversion;
use App\Models\JobHistory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class JobChainService
{
    public function restartJobChain(string $jobId): array
    {
        try {
            $parent = JobHistory::query()->where('uuid', $jobId)->whereNull('parent_uuid')->first();
            if (! $parent) {
                throw new RuntimeException("Job chain not found: {$jobId}");
            }

            $metadata = $parent->metadata ?? [];
            $plan = $metadata['plannedSteps'] ?? [];
            if (! isset($metadata['fileId'], $metadata['userId'], $metadata['pipeline'], $metadata['processingGeneration']) || ! $plan) {
                throw new RuntimeException('Job chain is missing durable source or pipeline context.');
            }
            if (! in_array($metadata['pipeline'], ['gemini', 'textract+openai', 'ocr-only', 'csv'], true)) {
                throw new RuntimeException('Unsupported processing pipeline.');
            }
            $file = File::withoutGlobalScope('user')->where('user_id', $metadata['userId'])->find($metadata['fileId']);
            if (! $file || (int) $parent->file_id !== (int) $file->id || ($file->meta['processing_generation'] ?? null) !== $metadata['processingGeneration']) {
                throw new RuntimeException('Job chain source is missing, foreign or superseded.');
            }

            $tasks = $parent->tasks()->get()->keyBy('uuid');
            $restartIndex = collect($plan)->search(fn (array $step): bool => $tasks->get($step['uuid'])?->status === 'failed');
            if ($restartIndex === false) {
                $restartIndex = collect($plan)->search(fn (array $step): bool => $tasks->get($step['uuid'])?->status !== 'completed');
            }
            if ($restartIndex === false) {
                throw new RuntimeException('No restart point found - all tasks are completed.');
            }

            $remaining = array_slice($plan, $restartIndex);
            $jobs = [];
            foreach ($remaining as $step) {
                $job = $this->restoreStep($step, $jobId, $metadata, $file);
                $job->uuid = $step['uuid'];
                $job->onQueue($tasks->get($step['uuid'])?->queue ?? $parent->queue);
                $jobs[] = $job;
            }

            $parent->tasks()->whereIn('uuid', array_column($remaining, 'uuid'))->where('status', '!=', 'completed')->update([
                'status' => 'pending', 'progress' => 0, 'exception' => null, 'started_at' => null, 'finished_at' => null,
            ]);
            $parent->update(['status' => 'pending', 'exception' => null, 'finished_at' => null]);
            Bus::chain($jobs)->dispatch();

            return [
                'success' => true,
                'message' => 'Job chain restarted from '.class_basename($remaining[0]['class']),
                'restart_point' => class_basename($remaining[0]['class']),
                'jobs_count' => count($jobs),
            ];
        } catch (Throwable $exception) {
            Log::error('Job chain restart failed', ['job_id' => $jobId, 'error' => $exception->getMessage()]);

            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    private function restoreStep(array $step, string $jobId, array $metadata, File $file): BaseJob
    {
        return match ($step['class']) {
            ProcessFile::class => new ProcessFile($jobId),
            ProcessFileGemini::class => new ProcessFileGemini($jobId),
            ProcessReceipt::class => new ProcessReceipt($jobId),
            MatchMerchant::class => new MatchMerchant($jobId),
            ProcessDocument::class => new ProcessDocument($jobId),
            AnalyzeDocument::class => new AnalyzeDocument($jobId),
            ProcessCsvImport::class => new ProcessCsvImport($jobId, $file->id),
            ApplyTags::class => new ApplyTags($jobId, $file, $metadata['metadata']['tagIds']),
            DeleteWorkingFiles::class => new DeleteWorkingFiles($jobId),
            UpdatePulseDavFileStatus::class => new UpdatePulseDavFileStatus($jobId, $file->id, $metadata['metadata']['pulseDavFileId'], $metadata['fileType']),
            ConvertOfficeFile::class => $this->restoreConversion($step, $jobId, $file),
            default => throw new RuntimeException('Unsupported job in the stored pipeline.'),
        };
    }

    private function restoreConversion(array $step, string $jobId, File $file): ConvertOfficeFile
    {
        $conversions = FileConversion::query()->where('file_id', $file->id)->where('metadata->chain_id', $jobId)->get();
        $conversion = $conversions->sole();
        $job = unserialize(base64_decode($conversion->metadata['resume_payload'], true), ['allowed_classes' => [ConvertOfficeFile::class]]);
        if (! $job instanceof ConvertOfficeFile || $job->uuid !== $step['uuid'] || $job->jobID !== $jobId) {
            throw new RuntimeException('Conversion resume context does not match the stored pipeline.');
        }
        $job->chained = [];

        return $job;
    }

    /**
     * Get job chain status information
     */
    public function getJobChainStatus(string $jobId): array
    {
        $parentJob = JobHistory::where('uuid', $jobId)->first();
        if (! $parentJob) {
            return ['found' => false];
        }

        $tasks = $parentJob->tasks()->orderBy('order_in_chain')->get();

        $status = [
            'found' => true,
            'job_id' => $jobId,
            'name' => $parentJob->name,
            'status' => $parentJob->status,
            'created_at' => $parentJob->created_at->toISOString(),
            'tasks' => [],
            'can_restart' => false,
        ];

        foreach ($tasks as $task) {
            $status['tasks'][] = [
                'uuid' => $task->uuid,
                'name' => $task->name,
                'status' => $task->status,
                'order' => $task->order_in_chain,
                'progress' => $task->progress,
                'started_at' => $task->started_at?->toISOString(),
                'finished_at' => $task->finished_at?->toISOString(),
                'exception' => $task->exception,
            ];

            // Check if chain can be restarted
            if ($task->status === 'failed') {
                $status['can_restart'] = true;
            }
        }

        return $status;
    }

    /**
     * Mark failed jobs for restart
     */
    public function markJobsForRestart(array $jobIds): array
    {
        $results = [];

        foreach ($jobIds as $jobId) {
            $results[$jobId] = $this->restartJobChain($jobId);
        }

        return $results;
    }
}
