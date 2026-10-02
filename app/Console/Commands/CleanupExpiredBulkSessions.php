<?php

namespace App\Console\Commands;

use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadSession;
use App\Services\BulkUpload\BulkUploadService;
use Illuminate\Console\Command;
use Throwable;

class CleanupExpiredBulkSessions extends Command
{
    protected $signature = 'bulk:cleanup-expired
        {--dry-run : Show pending cleanup without deleting}
        {--hours=48 : Clean sessions expired more than this many hours ago}';

    protected $description = 'Retry bulk source cleanup after cancellation, completion or expiry and upload grant expiration';

    public function handle(BulkUploadService $service): int
    {
        $cutoff = now()->subHours(max(0, (int) $this->option('hours')));
        BulkUploadSession::withoutGlobalScope('user')->where('cleanup_pending', true)
            ->where(fn ($query) => $query->where('expires_at', '<', $cutoff)->orWhereIn('status', [BulkUploadSessionStatus::Cancelled, BulkUploadSessionStatus::Completed, BulkUploadSessionStatus::Failed]))
            ->chunkById(100, function ($sessions) use ($service): void {
                foreach ($sessions as $session) {
                    if ($this->option('dry-run')) {
                        $this->line("Pending cleanup: {$session->uuid}");

                        continue;
                    }
                    try {
                        $service->cleanupSession($session);
                    } catch (Throwable $exception) {
                        $this->warn("Cleanup remains pending: {$session->uuid} ({$exception->getMessage()})");
                    }
                }
            });

        return self::SUCCESS;
    }
}
