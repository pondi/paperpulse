<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\OrganizationBackfill;
use App\Models\OrganizationRun;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\FolderOrganizationService;
use App\Services\OrganizationBackfillService;
use Illuminate\Console\Command;

class RepairOrganization extends Command
{
    protected $signature = 'organization:repair {--user=} {--limit=100}';

    protected $description = 'Automatically repair existing archive grouping using stored evidence';

    public function handle(OrganizationBackfillService $backfills): int
    {
        $owners = File::withoutGlobalScope('user')->whereIn('status', ['completed', 'needs_review'])
            ->where(fn ($query) => $query->whereNull('placement_source')->orWhere('placement_source', '!=', 'manual'))
            ->where(fn ($query) => $query->whereNull('meta->organization_grouping_version')->orWhere('meta->organization_grouping_version', '!=', FolderOrganizationService::GROUPING_VERSION))
            ->when($this->option('user'), fn ($query) => $query->where('user_id', $this->option('user')))
            ->whereDoesntHave('primaryFolder', fn ($query) => $query->withoutGlobalScope('user')->where(fn ($query) => $query->where('is_pinned', true)->orWhere('organization_source', 'manual')))
            ->distinct()->limit(max(1, (int) $this->option('limit')))->pluck('user_id');
        foreach ($owners as $userId) {
            (new File)->getConnection()->transaction(function () use ($userId, $backfills): void {
                User::query()->lockForUpdate()->findOrFail($userId);
                if (UserPreference::query()->where('user_id', $userId)->value('auto_organize_documents') === false
                    || OrganizationBackfill::withoutGlobalScope('user')->where('active_user_id', $userId)->exists()) {
                    return;
                }
                $run = OrganizationRun::withoutGlobalScope('user')->where('active_user_id', $userId)->first();
                if ($run && $run->status !== 'failed') {
                    return;
                }
                if ($run) {
                    $run->recommendations()->withoutGlobalScope('user')->whereIn('status', ['pending', 'conflict'])
                        ->update(['status' => 'declined', 'decided_at' => now(), 'decision_reason' => 'Superseded by automatic archive repair.']);
                    $run->update(['status' => 'completed', 'active_user_id' => null, 'completed_at' => now()]);
                }
                $backfills->start($userId, ['extract_missing' => false, 'max_calls' => 10, 'max_tokens' => 160000]);
            });
        }

        return self::SUCCESS;
    }
}
