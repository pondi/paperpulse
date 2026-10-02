<?php

namespace App\Models;

use App\Jobs\Search\ReindexFile;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SearchableFilePivot extends Pivot
{
    protected static function booted(): void
    {
        $reindex = static fn (self $pivot) => ReindexFile::forFile((int) $pivot->file_id);
        static::saved($reindex);
        static::deleted($reindex);
    }
}
