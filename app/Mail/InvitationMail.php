<?php

namespace App\Mail;

use App\Models\Invitation;

class InvitationMail extends BaseMail
{
    protected Invitation $invitation;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;

        // Prepare template variables
        $variables = [
            'invitation_url' => route('register', ['token' => $invitation->token]),
            'expires_at' => $invitation->expires_at->format('F j, Y \a\t g:i A'),
            'app_name' => config('app.name'),
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
        $registrationUrl = route('register', ['token' => $this->invitation->token]);
        $expiresAt = $this->invitation->expires_at->format('F j, Y \a\t g:i A');

        return '
            <h1>You\'re invited to join '.config('app.name').'</h1>
            <p>Good news! You\'ve been invited to experience effortless document management with '.config('app.name').'.</p>
            <p>Say goodbye to receipt chaos, missed tax deductions, and hours of manual paperwork. With '.config('app.name').', you can snap a photo, and we\'ll handle the rest.</p>
            <div class="text-center">
                <a href="'.$registrationUrl.'" class="btn">Accept Invitation & Get Started</a>
            </div>
            <div class="accent-box">
                <p style="margin: 0;"><strong>Important:</strong> This invitation expires on <strong>'.$expiresAt.'</strong>. Don\'t miss out!</p>
            </div>
            <p>Not interested? You can safely ignore this email.</p>
        ';
    }
}
