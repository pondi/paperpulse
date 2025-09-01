<?php

namespace App\Console\Commands;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendInvitation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invite:send {email} {--invited-by=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an invitation to register';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $invitedByUserId = $this->option('invited-by');

        // If no invited-by is provided and no users exist, allow creating first user
        if (! $invitedByUserId && User::count() === 0) {
            $invitedByUserId = null;
        } elseif (! $invitedByUserId) {
            $this->error('The --invited-by option is required when users already exist.');

            return 1;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return 1;
        }

        // Check if invitation already exists and is valid
        $existingInvitation = Invitation::where('email', $email)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if ($existingInvitation) {
            $this->error("A valid invitation for {$email} already exists.");

            return 1;
        }

        // Create invitation
        $invitation = Invitation::createForEmail($email, $invitedByUserId);

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

        $this->info('Invitation created successfully!');
        $this->line("Email: {$email}");
        $this->line("Registration URL: {$registrationUrl}");
        $this->line("Expires: {$invitation->expires_at->format('Y-m-d H:i:s')}");

        if ($inviter) {
            $this->line("Invited by: {$inviter->name}");
        }

        return 0;
    }
}
