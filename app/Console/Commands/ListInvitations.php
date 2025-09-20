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
    protected $signature = 'invite:list {--pending : Show only pending invitations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all invitations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Invitation::with('invitedBy');

        if ($this->option('pending')) {
            $query->where('expires_at', '>', now())
                ->whereNull('used_at');
        }

        $invitations = $query->get();

        if ($invitations->isEmpty()) {
            $this->info('No invitations found.');

            return 0;
        }

        $headers = ['Email', 'Status', 'Invited By', 'Created', 'Expires', 'Used At'];
        $data = [];

        foreach ($invitations as $invitation) {
            $status = 'Expired';
            if ($invitation->isUsed()) {
                $status = 'Used';
            } elseif ($invitation->isValid()) {
                $status = 'Pending';
            }

            $data[] = [
                $invitation->email,
                $status,
                $invitation->invitedBy->name ?? 'Unknown',
                $invitation->created_at->format('Y-m-d H:i:s'),
                $invitation->expires_at->format('Y-m-d H:i:s'),
                $invitation->used_at?->format('Y-m-d H:i:s') ?? '-',
            ];
        }

        $this->table($headers, $data);

        return 0;
    }
}
