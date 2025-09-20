<?php

namespace App\Console\Commands;

use App\Models\BetaRequest;
use Illuminate\Console\Command;

class ListBetaRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beta:list {--status=} {--pending} {--invited} {--rejected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List beta access requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = BetaRequest::query();

        // Filter by status
        if ($this->option('pending')) {
            $query->where('status', 'pending');
        } elseif ($this->option('invited')) {
            $query->where('status', 'invited');
        } elseif ($this->option('rejected')) {
            $query->where('status', 'rejected');
        } elseif ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        if ($requests->isEmpty()) {
            $this->info('No beta requests found.');

            return 0;
        }

        $headers = ['Name', 'Email', 'Company', 'Status', 'Requested At', 'Invited At'];
        $rows = [];

        foreach ($requests as $request) {
            $rows[] = [
                $request->name,
                $request->email,
                $request->company ?? '-',
                $request->status,
                $request->created_at->format('Y-m-d H:i'),
                $request->invited_at ? $request->invited_at->format('Y-m-d H:i') : '-',
            ];
        }

        $this->table($headers, $rows);

        // Show summary
        $this->newLine();
        $this->info('Summary:');
        $this->line('Total: '.$requests->count());

        $statusCounts = $requests->groupBy('status')->map->count();
        foreach ($statusCounts as $status => $count) {
            $this->line(ucfirst($status).': '.$count);
        }

        return 0;
    }
}
