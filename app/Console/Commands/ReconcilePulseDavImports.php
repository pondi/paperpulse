<?php

namespace App\Console\Commands;

use App\Models\PulseDavFile;
use App\Services\PulseDav\ImportService;
use Illuminate\Console\Command;

class ReconcilePulseDavImports extends Command
{
    protected $signature = 'pulsedav:reconcile-imports';

    protected $description = 'Reconcile scanner imports with durable processing results and abandoned claims';

    public function handle(): int
    {
        PulseDavFile::query()->whereIn('status', ['queued', 'processing', 'handed_off'])->chunkById(100, function ($files): void {
            foreach ($files as $file) {
                ImportService::reconcileFile($file);
            }
        });

        return self::SUCCESS;
    }
}
