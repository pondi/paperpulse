<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = [
            [
                'key' => 'invitation',
                'subject' => 'You\'re invited to join {{ app_name }}',
                'body' => '<h1>You\'re Invited!</h1><p><strong>{{ inviter_name }}</strong> has invited you to join {{ app_name }}.</p><p>PaperPulse helps you transform receipts and documents into organized, searchable intelligence using AI-powered OCR.</p><div class="text-center"><a href="{{ invitation_url }}" class="btn btn-accent">Accept Invitation</a></div><div class="accent-box"><p style="margin: 0;"><strong>Note:</strong> This invitation expires on {{ expires_at }}.</p></div>',
            ],
            [
                'key' => 'welcome',
                'subject' => 'Welcome to {{ app_name }}!',
                'body' => '<h1>Welcome to {{ app_name }}, {{ user_name }}!</h1><p>Thank you for joining us. We\'re excited to help you organize your receipts and documents with AI-powered intelligence.</p><h2>Get Started</h2><p>Here\'s what you can do:</p><div class="accent-box"><p style="margin: 0 0 8px 0;">📸 <strong>Snap receipts</strong> with your phone</p><p style="margin: 0 0 8px 0;">🤖 <strong>AI extracts</strong> merchant, amounts, and line items</p><p style="margin: 0;">🔍 <strong>Search instantly</strong> and export for taxes</p></div><div class="text-center"><a href="{{ dashboard_url }}" class="btn btn-accent">Go to Dashboard</a></div><p>If you have any questions, feel free to reach out to our support team.</p>',
            ],
            [
                'key' => 'receipt_processed_success',
                'subject' => 'Receipt Processed Successfully',
                'body' => '<h1>Receipt Processed Successfully</h1><p>Great news! Your receipt has been processed successfully and is now available in your account.</p><div class="accent-box"><p style="margin: 0 0 8px 0;"><strong>Merchant:</strong> {{ merchant_name }}</p><p style="margin: 0;"><strong>Amount:</strong> {{ amount }} {{ currency }}</p></div><p>You can now view, search, and manage this receipt from your dashboard.</p><div class="text-center"><a href="{{ receipt_url }}" class="btn btn-accent">View Receipt</a></div><p>Thank you for using PaperPulse!</p>',
            ],
            [
                'key' => 'receipt_processed_failed',
                'subject' => 'Receipt Processing Failed',
                'body' => '<h1>Receipt Processing Failed</h1><p>We encountered an error while processing your receipt.</p><div class="accent-box"><p style="margin: 0;"><strong>Error:</strong> {{ error_message }}</p></div><p>Please try uploading the receipt again or contact support if the issue persists.</p><div class="text-center"><a href="{{ upload_url }}" class="btn btn-accent">Upload New Receipt</a></div>',
            ],
            [
                'key' => 'bulk_operation_completed',
                'subject' => 'Bulk {{ operation_type }} completed',
                'body' => '<h1>Bulk Operation Completed</h1><p>Your bulk {{ operation_type }} operation has been completed successfully.</p><div class="accent-box"><p style="margin: 0;"><strong>Items Processed:</strong> {{ count }}</p></div><p>All items have been processed and are now available in your account.</p><div class="text-center"><a href="{{ dashboard_url }}" class="btn btn-accent">View Results</a></div>',
            ],
            [
                'key' => 'scanner_files_imported',
                'subject' => '{{ count }} new files imported from scanner',
                'body' => '<h1>Scanner Files Imported</h1><p>New files have been imported from your scanner and are ready for processing.</p><div class="accent-box"><p style="margin: 0;"><strong>Files Imported:</strong> {{ count }}</p></div><p>You can view and manage these files from your scanner imports dashboard.</p><div class="text-center"><a href="{{ dashboard_url }}" class="btn btn-accent">View Scanner Imports</a></div>',
            ],
            [
                'key' => 'weekly_summary',
                'subject' => 'Your weekly receipt summary ({{ week_start }} - {{ week_end }})',
                'body' => '<h1>Weekly Summary</h1><p>Here\'s your weekly receipt summary for <strong>{{ week_start }} - {{ week_end }}</strong>:</p><div class="accent-box"><p style="margin: 0 0 8px 0;"><strong>{{ total_receipts }}</strong> receipts processed</p><p style="margin: 0 0 8px 0;"><strong>{{ total_amount }}</strong> total spending</p><p style="margin: 0;"><strong>{{ average_amount }}</strong> average per receipt</p></div>{{ categories_summary }}{{ merchants_summary }}<div class="text-center"><a href="{{ receipts_url }}" class="btn btn-accent">View All Receipts</a></div>',
            ],
            [
                'key' => 'document_shared',
                'subject' => 'Document shared: {{ document_title }}',
                'body' => '<h1>Document Shared With You</h1><p><strong>{{ shared_by_name }}</strong> has shared a document with you.</p><div class="accent-box"><p style="margin: 0 0 8px 0;"><strong>Document:</strong> {{ document_title }}</p><p style="margin: 0;"><strong>Permission:</strong> {{ permission }}</p></div>{{ expires_info }}<div class="text-center"><a href="{{ document_url }}" class="btn btn-accent">View Document</a></div>',
            ],
            [
                'key' => 'receipt_shared',
                'subject' => 'Receipt shared: {{ merchant_name }}',
                'body' => '<h1>Receipt Shared With You</h1><p><strong>{{ shared_by_name }}</strong> has shared a receipt with you.</p><div class="accent-box"><p style="margin: 0 0 8px 0;"><strong>Merchant:</strong> {{ merchant_name }}</p><p style="margin: 0 0 8px 0;"><strong>Amount:</strong> {{ amount }} {{ currency }}</p><p style="margin: 0;"><strong>Permission:</strong> {{ permission }}</p></div>{{ expires_info }}<div class="text-center"><a href="{{ receipt_url }}" class="btn btn-accent">View Receipt</a></div>',
            ],
        ];

        foreach ($templates as $template) {
            DB::table('email_templates')
                ->where('key', $template['key'])
                ->update([
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'updated_at' => now(),
                ]);
        }

        // Clear email template cache
        DB::table('email_templates')->get()->each(function ($template) {
            Cache::forget("email_template_{$template->key}");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - keeping the new templates is safe
    }
};
