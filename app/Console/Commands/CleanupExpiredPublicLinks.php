<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PublicCollectionSharingService;
use Illuminate\Console\Command;

class CleanupExpiredPublicLinks extends Command
{
    protected $signature = 'public-links:cleanup';

    protected $description = 'Deactivate expired public collection links';

    public function handle(PublicCollectionSharingService $service): int
    {
        $count = $service->cleanupExpiredLinks();

        $this->info("Deactivated {$count} expired public link(s).");

        return self::SUCCESS;
    }
}
