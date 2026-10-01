<?php

namespace App\Console\Commands;

use App\Services\Files\FileProcessingRequestService;
use Illuminate\Console\Command;

class RecoverFileProcessingRequests extends Command
{
    protected $signature = 'files:recover-processing {--limit=100 : Maximum processing requests to recover}';

    protected $description = 'Retry durable upload processing handoffs and abandoned upload cleanup';

    public function handle(FileProcessingRequestService $requests): int
    {
        $this->info('Recovered '.$requests->recover(max(1, (int) $this->option('limit'))).' processing requests.');

        return self::SUCCESS;
    }
}
