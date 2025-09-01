<?php

namespace App\Mail;

use App\Models\User;

class WelcomeMail extends BaseMail
{
    protected User $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;

        // Prepare template variables
        $variables = [
            'user_name' => $user->name,
            'user_email' => $user->email,
        ];

        parent::__construct('welcome', $variables);
    }

    /**
     * Get fallback subject when template is not found
     */
    protected function getFallbackSubject(): string
    {
        return 'Welcome to ' . config('app.name') . '!';
    }

    /**
     * Get fallback body when template is not found
     */
    protected function getFallbackBody(): string
    {
        $dashboardUrl = route('dashboard');

        return "
            <h1>Welcome to " . config('app.name') . ", {$this->user->name}!</h1>
            <p>Thank you for joining us. We're excited to have you on board!</p>
            <p>
                Get started by uploading your first receipt or document to see how " . config('app.name') . " 
                can help you organize and manage your documents with AI-powered processing.
            </p>
            <p>
                <a href=\"{$dashboardUrl}\" class=\"btn\">Go to Dashboard</a>
            </p>
            <h2>What you can do with " . config('app.name') . ":</h2>
            <ul>
                <li><strong>Upload Receipts:</strong> Automatically extract key information like merchant, amount, and date</li>
                <li><strong>Manage Documents:</strong> Store and organize all your important documents</li>
                <li><strong>AI Processing:</strong> Let our AI categorize and extract data from your files</li>
                <li><strong>Search & Filter:</strong> Quickly find what you're looking for with powerful search</li>
                <li><strong>Share Securely:</strong> Share documents with controlled access and expiration</li>
            </ul>
            <p>If you have any questions or need help getting started, don't hesitate to reach out!</p>
        ";
    }
}