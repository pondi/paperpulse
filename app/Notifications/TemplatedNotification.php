<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

abstract class TemplatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the mail representation of the notification using templates
     */
    public function toMail($notifiable): MailMessage
    {
        $templateKey = $this->getEmailTemplateKey();
        $variables = $this->getEmailVariables($notifiable);

        // Try to get the template
        $template = EmailTemplate::getByKey($templateKey);

        if ($template) {
            // Render using template
            $rendered = $template->render($this->getAllVariables($variables, $notifiable));

            // Wrap the body content in the email layout
            $htmlContent = view('emails.layouts.base', [
                'content' => $rendered['body'],
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
            ])->render();

            return (new MailMessage)
                ->subject($rendered['subject'])
                ->view('emails.templated-html', ['htmlContent' => $htmlContent]);
        } else {
            // Fall back to original method if template doesn't exist
            Log::warning("Email template not found for notification: {$templateKey}");

            return $this->getFallbackMail($notifiable);
        }
    }

    /**
     * Get all variables including defaults
     */
    protected function getAllVariables(array $variables, $notifiable): array
    {
        return array_merge([
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'dashboard_url' => route('dashboard'),
            'receipts_url' => route('receipts.index'),
            'upload_url' => route('documents.upload'),
            'user_name' => $notifiable->name ?? '',
            'user_email' => $notifiable->email ?? '',
            'current_year' => date('Y'),
        ], $variables);
    }

    /**
     * Get the email template key for this notification
     */
    abstract protected function getEmailTemplateKey(): string;

    /**
     * Get the variables to pass to the email template
     */
    abstract protected function getEmailVariables($notifiable): array;

    /**
     * Get fallback mail message if template is not found
     */
    abstract protected function getFallbackMail($notifiable): MailMessage;
}
