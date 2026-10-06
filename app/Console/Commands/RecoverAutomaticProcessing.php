<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Services\Files\FileReprocessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Context;
use RuntimeException;

class RecoverAutomaticProcessing extends Command
{
    protected $signature = 'files:recover-automatic {--limit=25}';

    protected $description = 'Resolve old classification reviews and recover transient processing failures automatically';

    public function handle(FileReprocessingService $reprocessing): int
    {
        $files = File::withoutGlobalScope('user')->where(function ($query): void {
            $query->where(fn ($query) => $query->where('status', 'needs_review')->where('meta->review->reason', 'uncertain_classification'))
                ->orWhere(fn ($query) => $query->where('status', 'failed')->where('meta->last_processing_error->retryable', true));
        })->where(fn ($query) => $query->whereNull('meta->last_processing_error->retry_after')->orWhere('meta->last_processing_error->retry_after', '<=', now()->utc()->toIso8601String()))
            ->where(fn ($query) => $query->whereNull('meta->automatic_recovery_attempts')->orWhere('meta->automatic_recovery_attempts', '<', 2))
            ->where('updated_at', '<=', now()->subMinutes(15))->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        foreach ($files as $file) {
            $file->getConnection()->transaction(function () use ($file, $reprocessing): void {
                $locked = File::withoutGlobalScope('user')->where('user_id', $file->user_id)->lockForUpdate()->find($file->id);
                if (! $locked || ! in_array($locked->status, ['needs_review', 'failed'], true) || ($locked->meta['automatic_recovery_attempts'] ?? 0) >= 2) {
                    return;
                }
                $fileMeta = $locked->meta ?? [];
                $fileMeta['automatic_recovery_attempts'] = ($fileMeta['automatic_recovery_attempts'] ?? 0) + 1;
                if (($fileMeta['review']['reason'] ?? null) === 'uncertain_classification') {
                    unset($fileMeta['review']['corrected_type']);
                }
                $locked->update(['meta' => $fileMeta]);
                $result = $reprocessing->reprocessFile($locked, provider: null, skipActive: true);
                if (! $result['success']) {
                    Context::add('automatic_recovery', ['file_id' => $locked->id, 'user_id' => $locked->user_id, 'attempt' => $fileMeta['automatic_recovery_attempts']]);
                    report(new RuntimeException('Automatic file recovery could not dispatch processing.'));
                }
            });
        }

        return self::SUCCESS;
    }
}
