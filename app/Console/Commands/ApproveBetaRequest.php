<?php

namespace App\Console\Commands;

use App\Mail\InvitationMail;
use App\Models\BetaRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ApproveBetaRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beta:approve {email} {--invited-by=} {--reject}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve or reject a beta request and send invitation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $invitedByUserId = $this->option('invited-by');
        $reject = $this->option('reject');

        // Find the beta request
        $betaRequest = BetaRequest::where('email', $email)->first();

        if (! $betaRequest) {
            $this->error("No beta request found for email: {$email}");

            return 1;
        }

        if ($betaRequest->isInvited()) {
            $this->error("Beta request for {$email} has already been invited.");

            return 1;
        }

        if ($betaRequest->isRejected()) {
            $this->error("Beta request for {$email} has already been rejected.");

            return 1;
        }

        // Handle rejection
        if ($reject) {
            $betaRequest->markAsRejected();
            $this->info("Beta request for {$email} has been rejected.");

            return 0;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return 1;
        }

        // Check if invitation already exists
        $existingInvitation = Invitation::where('email', $email)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if ($existingInvitation) {
            $this->error("A valid invitation for {$email} already exists.");

            return 1;
        }

        // Create invitation using existing logic
        $invitation = Invitation::createForEmail($email, $invitedByUserId);

        // Mark beta request as invited
        $betaRequest->markAsInvited($invitedByUserId);

        // Get inviter user if provided
        $inviter = $invitedByUserId ? User::find($invitedByUserId) : null;

        // Send invitation email
        try {
            Mail::to($email)->send(new InvitationMail($invitation, $inviter));
            $this->info('Invitation email sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send invitation email: '.$e->getMessage());
            $this->warn('However, the invitation record has been created.');
        }

        // Generate registration URL
        $registrationUrl = route('register', ['token' => $invitation->token]);

        $this->info('Beta request approved and invitation created!');
        $this->line("Name: {$betaRequest->name}");
        $this->line("Email: {$email}");
        if ($betaRequest->company) {
            $this->line("Company: {$betaRequest->company}");
        }
        $this->line("Registration URL: {$registrationUrl}");
        $this->line("Expires: {$invitation->expires_at->format('Y-m-d H:i:s')}");

        if ($inviter) {
            $this->line("Invited by: {$inviter->name}");
        }

        return 0;
    }
}
