<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;

class ListInvitations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:list {--status= : Filter by status (pending, sent, rejected)} {--pending : Show only pending requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all invitation requests and sent invitations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Invitation::orderBy('created_at', 'desc');

        // Filter by status
        if ($status = $this->option('status')) {
            $query->where('status', $status);
        } elseif ($this->option('pending')) {
            $query->where('status', 'pending');
        }

        $invitations = $query->get();

        if ($invitations->isEmpty()) {
            $this->info('No invitations found.');

            return 0;
        }

        $headers = ['Name', 'Email', 'Company', 'Status', 'Created', 'Sent At', 'Used At'];
        $data = [];

        foreach ($invitations as $invitation) {
            $data[] = [
                $invitation->name ?? '-',
                $invitation->email,
                $invitation->company ?? '-',
                ucfirst($invitation->status),
                $invitation->created_at->format('Y-m-d H:i'),
                $invitation->sent_at?->format('Y-m-d H:i') ?? '-',
                $invitation->used_at?->format('Y-m-d H:i') ?? '-',
            ];
        }

        $this->table($headers, $data);

        // Show summary
        $this->newLine();
        $this->info('Summary:');
        $this->line('Total: '.$invitations->count());

        $statusCounts = $invitations->groupBy('status')->map->count();
        foreach ($statusCounts as $status => $count) {
            $this->line(ucfirst($status).': '.$count);
        }

        return 0;
    }
}
