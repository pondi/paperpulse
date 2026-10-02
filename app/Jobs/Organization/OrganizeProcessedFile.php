<?php

namespace App\Jobs\Organization;

use App\Models\File;
use App\Models\FileOrganizationRequest;
use App\Models\User;
use App\Services\FileOrganizationSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrganizeProcessedFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120];

    public function __construct(public int $requestId)
    {
        $this->onConnection('database');
    }

    public function handle(FileOrganizationSummaryService $summaries): void
    {
        try {
            (new FileOrganizationRequest)->getConnection()->transaction(function () use ($summaries): void {
                $request = FileOrganizationRequest::query()->lockForUpdate()->find($this->requestId);
                if (! $request || in_array($request->status, ['completed', 'obsolete'], true)) {
                    return;
                }
                User::query()->lockForUpdate()->findOrFail($request->user_id);
                $file = File::withoutGlobalScope('user')->where('user_id', $request->user_id)->lockForUpdate()->find($request->file_id);
                if (! $file || ($file->meta['processing_generation'] ?? null) !== $request->generation) {
                    $request->update(['status' => 'obsolete']);

                    return;
                }
                $entity = $file->primaryEntity()->where('user_id', $request->user_id)->with('entity')->first()?->entity;
                if (! $entity) {
                    throw new RuntimeException('Completed extraction has no primary entity.');
                }
                $summaries->capture($file, $entity);
                $request->update(['status' => 'completed', 'last_error' => null]);
            });
        } catch (Throwable $exception) {
            FileOrganizationRequest::query()->whereKey($this->requestId)->update([
                'status' => 'failed', 'last_error' => Str::limit($exception->getMessage(), 1000),
            ]);
            throw $exception;
        }
    }
}
