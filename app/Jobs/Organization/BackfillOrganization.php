<?php

namespace App\Jobs\Organization;

use App\Models\OrganizationBackfill;
use App\Services\AI\Shared\ProcessingUsageBudget;
use App\Services\OrganizationBackfillService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BackfillOrganization implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 360;

    public int $uniqueFor = 600;

    public function __construct(public int $userId, public int $backfillId)
    {
        $this->onConnection('database')->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->backfillId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(OrganizationBackfillService $backfills): void
    {
        try {
            $backfills->process($this->userId, $this->backfillId);
        } catch (Throwable $exception) {
            $usage = ProcessingUsageBudget::usage($this->userId, 'backfill:'.$this->backfillId);
            OrganizationBackfill::withoutGlobalScope('user')->where('user_id', $this->userId)->whereKey($this->backfillId)
                ->where('status', 'running')->update(['status' => 'failed', 'calls' => $usage['calls'], 'tokens' => $usage['reserved_tokens'], 'error' => 'Backfill failed. Review its budget and resume to continue from the saved position.']);
            throw $exception;
        }
    }
}
