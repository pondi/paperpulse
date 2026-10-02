<?php

namespace App\Listeners;

use App\Events\FileExtractionCompleted;
use App\Jobs\Organization\OrganizeProcessedFile;
use App\Models\FileOrganizationRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueFileOrganization
{
    public function handle(FileExtractionCompleted $event): void
    {
        try {
            (new FileOrganizationRequest)->getConnection()->transaction(function () use ($event): void {
                $request = FileOrganizationRequest::query()->where('user_id', $event->userId)
                    ->where('file_id', $event->fileId)->where('generation', $event->generation)->lockForUpdate()->first();
                if (! $request || in_array($request->status, ['completed', 'obsolete'], true)
                    || ($request->status === 'queued' && $request->updated_at->gt(now()->subMinutes(10)))) {
                    return;
                }
                OrganizeProcessedFile::dispatch($request->id)->beforeCommit();
                $request->update(['status' => 'queued', 'last_error' => null]);
            });
        } catch (Throwable $exception) {
            Log::warning('File organization handoff will be retried', ['file_id' => $event->fileId, 'exception' => $exception::class]);
        }
    }
}
