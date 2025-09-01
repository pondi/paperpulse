<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected string $templateKey;

    protected array $templateVariables = [];

    protected ?EmailTemplate $emailTemplate = null;

    /**
     * Create a new message instance.
     */
    public function __construct(string $templateKey, array $variables = [])
    {
        $this->templateKey = $templateKey;
        $this->templateVariables = $variables;

        // Load the email template
        $this->emailTemplate = EmailTemplate::getByKey($templateKey);

        if (! $this->emailTemplate) {
            Log::warning("Email template not found: {$templateKey}");
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->getRenderedSubject();

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->getRenderedBody(),
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get the rendered subject line
     */
    protected function getRenderedSubject(): string
    {
        if (! $this->emailTemplate) {
            return $this->getFallbackSubject();
        }

        $rendered = $this->emailTemplate->render($this->getAllVariables());

        return $rendered['subject'];
    }

    /**
     * Get the rendered email body
     */
    protected function getRenderedBody(): string
    {
        if (! $this->emailTemplate) {
            return $this->getFallbackBody();
        }

        $rendered = $this->emailTemplate->render($this->getAllVariables());

        return $this->wrapInLayout($rendered['body']);
    }

    /**
     * Get all variables including default app variables
     */
    protected function getAllVariables(): array
    {
        return array_merge($this->getDefaultVariables(), $this->templateVariables);
    }

    /**
     * Get default variables available to all templates
     */
    protected function getDefaultVariables(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'dashboard_url' => route('dashboard'),
            'receipts_url' => route('receipts.index'),
            'upload_url' => route('documents.upload'),
            'current_year' => date('Y'),
        ];
    }

    /**
     * Wrap content in email layout
     */
    protected function wrapInLayout(string $content): string
    {
        $layoutContent = view('emails.layouts.base', [
            'content' => $content,
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
        ])->render();

        return $layoutContent;
    }

    /**
     * Get fallback subject when template is not found
     */
    abstract protected function getFallbackSubject(): string;

    /**
     * Get fallback body when template is not found
     */
    abstract protected function getFallbackBody(): string;

    /**
     * Set additional template variables
     */
    public function with(array $variables): self
    {
        $this->templateVariables = array_merge($this->templateVariables, $variables);

        return $this;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email sending failed', [
            'template' => $this->templateKey,
            'variables' => $this->templateVariables,
            'error' => $exception->getMessage(),
        ]);
    }
}
