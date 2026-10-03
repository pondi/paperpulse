<?php

namespace App\Console\Commands;

use App\Models\ArchiveExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredExports extends Command
{
    protected $signature = 'exports:cleanup';

    protected $description = 'Remove expired private archive exports';

    public function handle(): int
    {
        foreach (ArchiveExport::where('expires_at', '<=', now())->lazyById(100) as $export) {
            if (Storage::disk('local')->deleteDirectory('private/exports/'.$export->user_id.'/'.$export->id)) {
                $export->delete();
            }
        }

        return self::SUCCESS;
    }
}
