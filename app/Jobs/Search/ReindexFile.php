<?php

namespace App\Jobs\Search;

use App\Models\File;
use App\Services\Search\SearchFacetService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;

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
        (new File)->getConnection()->afterCommit(static function () use ($fileId): void {
            $userId = File::withoutGlobalScope('user')->whereKey($fileId)->value('user_id');
            if ($userId) {
                SearchFacetService::invalidate($userId);
                self::dispatch($fileId);
            }
        });
    }

    public function handle(): void
    {
        $file = File::withoutGlobalScope('user')->find($this->fileId);
        if (! $file) {
            return;
        }
        foreach (Relation::morphMap() as $model) {
            $relations = ['file.collections', 'tags'];
            foreach (['merchant', 'category', 'lineItems'] as $relation) {
                if (method_exists($model, $relation)) {
                    $relations[] = $relation;
                }
            }
            $model::withoutGlobalScope('user')->with($relations)->where('user_id', $file->user_id)->where('file_id', $file->id)
                ->chunkById(100, static fn ($entities) => $entities->filter->shouldBeSearchable()->searchable());
        }
        SearchFacetService::invalidate($file->user_id);
    }
}
