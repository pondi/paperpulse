<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\User;

class InvitationMail extends BaseMail
{
    protected Invitation $invitation;

    protected ?User $inviter;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation, ?User $inviter = null)
    {
        $this->invitation = $invitation;
        $this->inviter = $inviter;

        // Prepare template variables
        $variables = [
            'invitation_url' => route('register', ['token' => $invitation->token]),
            'expires_at' => $invitation->expires_at->format('F j, Y \a\t g:i A'),
            'inviter_name' => $inviter ? $inviter->name : 'Administrator',
        ];

        parent::__construct('invitation', $variables);
    }

    /**
     * Get fallback subject when template is not found
     */
    protected function getFallbackSubject(): string
    {
        return 'You\'re invited to join '.config('app.name');
    }

    /**
     * Get fallback body when template is not found
     */
    protected function getFallbackBody(): string
    {
        $inviterName = $this->inviter ? $this->inviter->name : 'Administrator';
        $registrationUrl = route('register', ['token' => $this->invitation->token]);
        $expiresAt = $this->invitation->expires_at->format('F j, Y \a\t g:i A');

        return '
            <h1>Welcome to '.config('app.name')."!</h1>
            <p>{$inviterName} has invited you to join ".config('app.name').".</p>
            <p>
                <a href=\"{$registrationUrl}\" class=\"btn\">Accept Invitation</a>
            </p>
            <p>This invitation expires on {$expiresAt}.</p>
            <p>If you're not interested in joining, you can safely ignore this email.</p>
        ";
    }
}
