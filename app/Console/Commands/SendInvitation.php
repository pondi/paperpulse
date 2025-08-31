<?php

namespace App\Console\Commands;

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
    protected $signature = 'invite:send {email} {--invited-by=1}';

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
        
        // Generate registration URL
        $registrationUrl = route('register', ['token' => $invitation->token]);
        
        $this->info("Invitation created successfully!");
        $this->line("Email: {$email}");
        $this->line("Registration URL: {$registrationUrl}");
        $this->line("Expires: {$invitation->expires_at->format('Y-m-d H:i:s')}");
        
        // In a real application, you would send an email here
        // Mail::to($email)->send(new InvitationMail($invitation, $registrationUrl));
        
        return 0;
    }
}
