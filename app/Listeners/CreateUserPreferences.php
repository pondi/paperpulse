<?php

namespace App\Listeners;

use App\Mail\WelcomeMail;
use App\Models\UserPreference;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateUserPreferences
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        // Create default preferences for the new user if they don't already exist
        if (! $event->user->preferences()->exists()) {
            $event->user->preferences()->create(UserPreference::defaultPreferences());
        }

        // Send welcome email
        try {
            Mail::to($event->user->email)->send(new WelcomeMail($event->user));
            Log::info('Welcome email sent to user', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
