<?php

namespace App\Console\Commands;

use App\Services\SharingService;
use Illuminate\Console\Command;

class CleanupExpiredShares extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shares:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired file shares';

    protected SharingService $sharingService;

    /**
     * Create a new command instance.
     */
    public function __construct(SharingService $sharingService)
    {
        parent::__construct();
        $this->sharingService = $sharingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up expired shares...');

        $deletedCount = $this->sharingService->cleanupExpiredShares();

        $this->info("Deleted {$deletedCount} expired shares.");

        return Command::SUCCESS;
    }
}
