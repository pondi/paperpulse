<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredBulkSessions extends Command
{
    protected $signature = 'bulk:cleanup-expired
        {--dry-run : Show what would be cleaned up without deleting}
        {--hours=48 : Clean sessions expired more than this many hours ago}';

    protected $description = 'Clean up expired bulk upload sessions and their orphaned S3 files';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $hours = (int) $this->option('hours');

        $cutoff = now()->subHours($hours);

        $sessions = BulkUploadSession::withoutGlobalScopes()
            ->where('expires_at', '<', $cutoff)
            ->whereNotIn('status', [
                BulkUploadSessionStatus::Completed,
                BulkUploadSessionStatus::Cancelled,
            ])
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No expired sessions to clean up.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Found {$sessions->count()} expired sessions to clean up.");

        $totalFilesDeleted = 0;
        $totalS3Cleaned = 0;

        foreach ($sessions as $session) {
            /** @var BulkUploadSession $session */
            $this->line("  Session {$session->uuid} (user {$session->user_id}, expired {$session->expires_at->diffForHumans()})");

            $pendingFiles = $session->files()
                ->whereNotIn('status', [
                    BulkUploadFileStatus::Completed,
                    BulkUploadFileStatus::Processing,
                    BulkUploadFileStatus::Duplicate,
                ])
                ->get();

            $this->line("    {$pendingFiles->count()} unprocessed files to clean up");

            if (! $dryRun) {
                $disk = Storage::disk('uplink');

                /** @var BulkUploadFile $file */
                foreach ($pendingFiles as $file) {
                    if ($file->s3_key) {
                        try {
                            if ($disk->exists($file->s3_key)) {
                                $disk->delete($file->s3_key);
                                $totalS3Cleaned++;
                            }
                        } catch (\Exception $e) {
                            $this->warn("    Failed to delete S3 file {$file->s3_key}: {$e->getMessage()}");
                        }
                    }

                    $file->update(['status' => BulkUploadFileStatus::Skipped]);
                    $totalFilesDeleted++;
                }

                $session->update([
                    'status' => BulkUploadSessionStatus::Cancelled,
                ]);
                $session->refreshCounts();
            } else {
                $totalFilesDeleted += $pendingFiles->count();
            }
        }

        $prefix = $dryRun ? '[DRY RUN] Would clean' : 'Cleaned';
        $this->info("{$prefix}: {$sessions->count()} sessions, {$totalFilesDeleted} files, {$totalS3Cleaned} S3 objects.");

        Log::info('[BulkCleanup] Expired sessions cleaned', [
            'sessions' => $sessions->count(),
            'files' => $totalFilesDeleted,
            's3_objects' => $totalS3Cleaned,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
