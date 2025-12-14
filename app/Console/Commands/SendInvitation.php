<?php

namespace App\Console\Commands;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInvitation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:send {email} {--reject}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an invitation to register or approve/reject a pending request';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $reject = $this->option('reject');

        // Check for existing invitation request first
        $existingInvitation = Invitation::where('email', $email)->first();

        // Handle rejection
        if ($reject) {
            if (! $existingInvitation) {
                $this->error("No invitation request found for email: {$email}");

                return 1;
            }

            if ($existingInvitation->isRejected()) {
                $this->error("Invitation request for {$email} has already been rejected.");

                return 1;
            }

            if ($existingInvitation->isSent()) {
                $this->error("Invitation for {$email} has already been sent. Cannot reject.");

                return 1;
            }

            $existingInvitation->markAsRejected();
            $this->info("Invitation request for {$email} has been rejected.");

            return 0;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return 1;
        }

        // If invitation request exists, approve it; otherwise create new one
        if ($existingInvitation) {
            if ($existingInvitation->isSent()) {
                $this->error("Invitation for {$email} has already been sent.");

                return 1;
            }

            if ($existingInvitation->isRejected()) {
                $this->error("Invitation request for {$email} has been rejected. Cannot send.");

                return 1;
            }

            // Approve the pending request
            $existingInvitation->markAsSent();
            $invitation = $existingInvitation->fresh();
            $this->info("Pending invitation request approved!");
        } else {
            // Create new invitation directly
            $invitation = Invitation::create([
                'email' => $email,
                'status' => 'sent',
            ]);
            $invitation->markAsSent();
            $invitation = $invitation->fresh();
        }

        // Send invitation email
        try {
            Mail::to($email)->send(new InvitationMail($invitation));
            $this->info('Invitation email sent successfully!');
        } catch (Exception $e) {
            $this->error('Failed to send invitation email: '.$e->getMessage());
            $this->warn('However, the invitation record has been created.');
        }

        // Generate registration URL
        $registrationUrl = route('register', ['token' => $invitation->token]);

        $this->info('Invitation sent successfully!');
        if ($invitation->name) {
            $this->line("Name: {$invitation->name}");
        }
        $this->line("Email: {$email}");
        if ($invitation->company) {
            $this->line("Company: {$invitation->company}");
        }
        $this->line("Registration URL: {$registrationUrl}");
        $this->line("Expires: {$invitation->expires_at->format('Y-m-d H:i:s')}");

        return 0;
    }
}
