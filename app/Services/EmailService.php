<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send an email using a template
     */
    public function sendTemplatedEmail(
        string $to,
        string $templateKey,
        array $variables = [],
        ?string $mailer = null
    ): bool {
        try {
            $template = EmailTemplate::getByKey($templateKey);

            if (! $template) {
                Log::error("Email template not found: {$templateKey}");

                return false;
            }

            // Validate variables
            $missing = $template->validateVariables($variables);
            if (! empty($missing)) {
                Log::warning('Missing email template variables', [
                    'template' => $templateKey,
                    'missing' => $missing,
                ]);
            }

            // Create a generic mailable
            $mailable = new \App\Mail\TemplatedMail($templateKey, $variables);

            if ($mailer) {
                Mail::mailer($mailer)->to($to)->send($mailable);
            } else {
                Mail::to($to)->send($mailable);
            }

            Log::info('Email sent successfully', [
                'template' => $templateKey,
                'to' => $to,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'template' => $templateKey,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email to a user
     */
    public function sendToUser(
        User $user,
        string $templateKey,
        array $variables = [],
        ?string $mailer = null
    ): bool {
        // Add user-specific variables
        $variables = array_merge([
            'user_name' => $user->name,
            'user_email' => $user->email,
        ], $variables);

        return $this->sendTemplatedEmail($user->email, $templateKey, $variables, $mailer);
    }

    /**
     * Send bulk emails
     */
    public function sendBulkEmails(
        array $recipients,
        string $templateKey,
        array $baseVariables = [],
        ?string $mailer = null
    ): array {
        $results = [];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : (is_string($recipient) ? $recipient : $recipient->email);
            $variables = is_array($recipient) && isset($recipient['variables'])
                ? array_merge($baseVariables, $recipient['variables'])
                : $baseVariables;

            $results[$email] = $this->sendTemplatedEmail($email, $templateKey, $variables, $mailer);
        }

        return $results;
    }

    /**
     * Preview an email template
     */
    public function previewTemplate(string $templateKey, array $variables = []): ?array
    {
        $template = EmailTemplate::getByKey($templateKey);

        if (! $template) {
            return null;
        }

        // Add default variables
        $allVariables = array_merge([
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'dashboard_url' => route('dashboard'),
            'current_year' => date('Y'),
        ], $variables);

        return $template->render($allVariables);
    }

    /**
     * Get all available templates
     */
    public function getAvailableTemplates(): \Illuminate\Database\Eloquent\Collection
    {
        return EmailTemplate::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Get template by key
     */
    public function getTemplate(string $key): ?EmailTemplate
    {
        return EmailTemplate::getByKey($key);
    }

    /**
     * Update or create template
     */
    public function upsertTemplate(
        string $key,
        string $name,
        string $subject,
        string $body,
        array $variables = [],
        ?string $description = null
    ): EmailTemplate {
        return EmailTemplate::updateOrCreate(
            ['key' => $key],
            [
                'name' => $name,
                'subject' => $subject,
                'body' => $body,
                'variables' => $variables,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }

    /**
     * Disable template
     */
    public function disableTemplate(string $key): bool
    {
        $template = EmailTemplate::where('key', $key)->first();

        if (! $template) {
            return false;
        }

        $template->update(['is_active' => false]);

        return true;
    }

    /**
     * Enable template
     */
    public function enableTemplate(string $key): bool
    {
        $template = EmailTemplate::where('key', $key)->first();

        if (! $template) {
            return false;
        }

        $template->update(['is_active' => true]);

        return true;
    }

    /**
     * Get email queue statistics
     */
    public function getQueueStats(): array
    {
        // Basic queue status implementation
        return [
            'pending' => 0,
            'processed' => 0,
            'failed' => 0,
        ];
    }
}
