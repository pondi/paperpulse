<?php

namespace App\Listeners;

use App\Models\UserPreference;
use Illuminate\Auth\Events\Registered;

class CreateUserPreferences
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        // Create default preferences for the new user
        $event->user->preferences()->create(UserPreference::defaultPreferences());
    }
}
