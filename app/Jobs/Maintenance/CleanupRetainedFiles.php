<?php

namespace App\Jobs\Maintenance;

use App\Jobs\BaseJob;
use App\Models\File;
use App\Models\User;
use App\Services\Files\FileDeletionService;
use Illuminate\Support\Str;

class CleanupRetainedFiles extends BaseJob
{
    public function __construct()
    {
        parent::__construct(Str::uuid());
        $this->jobName = 'Cleanup Retained Files';
    }

    protected function handleJob(): void
    {
        User::query()->whereHas('preferences', fn ($query) => $query
            ->where('delete_after_processing', true)->where('file_retention_days', '>', 0))
            ->with('preferences')->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $cutoff = now()->subDays($user->preference('file_retention_days', 30));
                    $sourceOnly = $user->preference('retention_mode', 'source_only') === 'source_only';
                    File::withoutGlobalScope('user')->where('user_id', $user->id)->retainable($cutoff)
                        ->chunkById(100, function ($files) use ($user, $cutoff, $sourceOnly): void {
                            foreach ($files as $file) {
                                app(FileDeletionService::class)->retainFile($file, $user->id, $cutoff, $sourceOnly);
                            }
                        });
                }
            });
    }
}
