<?php

namespace App\Models;

use App\Jobs\Search\ReindexFile;
use App\Services\OrganizationRevisionService;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SearchableFilePivot extends Pivot
{
    protected static function booted(): void
    {
        $reindex = static function (self $pivot): void {
            ReindexFile::forFile((int) $pivot->file_id);
            if ($pivot->getTable() === 'collection_file') {
                $file = File::withoutGlobalScope('user')->findOrFail($pivot->file_id);
                app(OrganizationRevisionService::class)->record($file);
            }
        };
        static::saved($reindex);
        static::deleted($reindex);
    }
}
