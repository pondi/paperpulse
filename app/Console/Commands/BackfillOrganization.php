<?php

namespace App\Console\Commands;

use App\Jobs\Organization\BackfillOrganization as BackfillJob;
use App\Models\OrganizationBackfill;
use App\Models\User;
use App\Services\OrganizationBackfillService;
use Illuminate\Console\Command;

class BackfillOrganization extends Command
{
    protected $signature = 'organization:backfill {user?} {--apply} {--resume=} {--recover} {--extract-missing} {--max-calls=10} {--max-tokens=160000}';

    protected $description = 'Preview or queue an opt-in archive backfill using stored grouping summaries';

    public function handle(OrganizationBackfillService $backfills): int
    {
        if ($this->option('recover')) {
            OrganizationBackfill::withoutGlobalScope('user')->where('status', 'running')->where('started_at', '<', now()->subMinutes(10))->update(['status' => 'failed']);
            foreach (OrganizationBackfill::withoutGlobalScope('user')->whereIn('status', ['queued', 'failed'])->where('attempts', '<', 3)->lazyById(100) as $backfill) {
                BackfillJob::dispatch($backfill->user_id, $backfill->id)->afterCommit();
            }

            return self::SUCCESS;
        }
        $userId = (int) $this->argument('user');
        if (! User::query()->whereKey($userId)->exists()) {
            $this->error('Select an existing user.');

            return self::FAILURE;
        }
        $options = ['extract_missing' => (bool) $this->option('extract-missing'), 'max_calls' => (int) $this->option('max-calls'), 'max_tokens' => (int) $this->option('max-tokens')];
        if ($options['max_calls'] < 1 || $options['max_calls'] > 100 || $options['max_tokens'] < 10000 || $options['max_tokens'] > 1000000) {
            $this->error('Use a budget of 1–100 calls and 10000–1000000 reserved tokens.');

            return self::FAILURE;
        }
        if (! $this->option('apply') && ! $this->option('resume')) {
            $this->line(json_encode($backfills->preview($userId), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        $backfill = $this->option('resume') ? $backfills->resume($userId, (int) $this->option('resume'), $options) : $backfills->start($userId, $options);
        $this->info('Queued archive backfill '.$backfill->id.'.');

        return self::SUCCESS;
    }
}
