<?php

namespace App\Jobs\Search;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ReindexFile implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(public int $fileId) {}

    public function uniqueId(): string
    {
        return (string) $this->fileId;
    }

    public static function forFile(int $fileId): void
    {
        (new File)->getConnection()->afterCommit(static fn () => self::dispatch($fileId));
    }

    public function handle(): void
    {
        $file = File::withoutGlobalScope('user')->find($this->fileId);
        if (! $file) {
            return;
        }
        foreach (Relation::morphMap() as $model) {
            $model::withoutGlobalScope('user')->where('user_id', $file->user_id)->where('file_id', $file->id)
                ->chunkById(100, static fn ($entities) => $entities->filter->shouldBeSearchable()->searchable());
        }
        Cache::tags(['search_facets:'.$file->user_id])->flush();
    }
}
