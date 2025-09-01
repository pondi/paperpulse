<?php

namespace App\Mail;

class TemplatedMail extends BaseMail
{
    /**
     * Get fallback subject when template is not found
     */
    protected function getFallbackSubject(): string
    {
        return 'Notification from '.config('app.name');
    }

    /**
     * Get fallback body when template is not found
     */
    protected function getFallbackBody(): string
    {
        return '<h1>Notification</h1><p>This is a notification from '.config('app.name').'.</p><p>The email template could not be loaded.</p>';
    }
}
