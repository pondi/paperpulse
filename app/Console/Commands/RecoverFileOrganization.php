<?php

namespace App\Console\Commands;

use App\Events\FileExtractionCompleted;
use App\Models\FileOrganizationRequest;
use Illuminate\Console\Command;

class RecoverFileOrganization extends Command
{
    protected $signature = 'organization:recover-placements {--limit=100}';

    protected $description = 'Retry pending, failed, or abandoned file organization requests';

    public function handle(): int
    {
        $requests = FileOrganizationRequest::query()->where(function ($query): void {
            $query->whereIn('status', ['pending', 'failed'])->orWhere(function ($query): void {
                $query->where('status', 'queued')->where('updated_at', '<=', now()->subMinutes(10));
            });
        })->orderBy('updated_at')->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
        foreach ($requests as $request) {
            FileExtractionCompleted::dispatch($request->user_id, $request->file_id, $request->generation);
        }

        return self::SUCCESS;
    }
}
