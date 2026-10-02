<?php

namespace App\Console\Commands;

use App\Enums\BulkUploadFileStatus;
use App\Models\BulkUploadSession;
use App\Services\BulkUpload\BulkUploadService;
use Illuminate\Console\Command;

class ReconcileBulkUploads extends Command
{
    protected $signature = 'bulk:reconcile';

    protected $description = 'Reconcile bulk uploads with durable processing results';

    public function handle(BulkUploadService $service): int
    {
        BulkUploadSession::withoutGlobalScope('user')->whereHas('files', fn ($query) => $query->withoutGlobalScope('user')
            ->whereNotNull('job_id')->whereIn('status', [BulkUploadFileStatus::Processing, BulkUploadFileStatus::Failed]))
            ->chunkById(100, function ($sessions) use ($service): void {
                foreach ($sessions as $session) {
                    $service->reconcileSession($session);
                }
            });

        return self::SUCCESS;
    }
}
