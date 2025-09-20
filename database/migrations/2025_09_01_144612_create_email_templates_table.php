<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default templates
        DB::table('email_templates')->insert([
            [
                'key' => 'invitation',
                'name' => 'Invitation Email',
                'subject' => 'You\'re invited to join {{ app_name }}',
                'body' => '<h1>Welcome to {{ app_name }}!</h1><p>{{ inviter_name }} has invited you to join {{ app_name }}.</p><p><a href="{{ invitation_url }}">Accept Invitation</a></p><p>This invitation expires on {{ expires_at }}.</p>',
                'variables' => json_encode(['app_name', 'inviter_name', 'invitation_url', 'expires_at']),
                'description' => 'Email sent when a user is invited to join the application',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {{ app_name }}!',
                'body' => '<h1>Welcome to {{ app_name }}, {{ user_name }}!</h1><p>Thank you for joining us. Get started by uploading your first receipt or document.</p><p><a href="{{ dashboard_url }}">Go to Dashboard</a></p>',
                'variables' => json_encode(['app_name', 'user_name', 'dashboard_url']),
                'description' => 'Email sent to new users after registration',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'receipt_processed_success',
                'name' => 'Receipt Processed Successfully',
                'subject' => 'Receipt Processed Successfully',
                'body' => '<h1>Your receipt has been processed!</h1><p>We\'ve successfully processed your receipt from {{ merchant_name }}.</p><p><strong>Amount:</strong> {{ amount }} {{ currency }}</p><p><a href="{{ receipt_url }}">View Receipt</a></p>',
                'variables' => json_encode(['merchant_name', 'amount', 'currency', 'receipt_url']),
                'description' => 'Email sent when a receipt is successfully processed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'receipt_processed_failed',
                'name' => 'Receipt Processing Failed',
                'subject' => 'Receipt Processing Failed',
                'body' => '<h1>Receipt processing failed</h1><p>We encountered an error while processing your receipt.</p><p><strong>Error:</strong> {{ error_message }}</p><p>Please try uploading the receipt again or contact support if the issue persists.</p><p><a href="{{ upload_url }}">Upload New Receipt</a></p>',
                'variables' => json_encode(['error_message', 'upload_url']),
                'description' => 'Email sent when receipt processing fails',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'bulk_operation_completed',
                'name' => 'Bulk Operation Completed',
                'subject' => 'Bulk {{ operation_type }} completed',
                'body' => '<h1>Bulk operation completed</h1><p>Your bulk {{ operation_type }} operation has been completed.</p><p><strong>Items processed:</strong> {{ count }}</p><p><a href="{{ dashboard_url }}">View Results</a></p>',
                'variables' => json_encode(['operation_type', 'count', 'dashboard_url']),
                'description' => 'Email sent when a bulk operation completes',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'scanner_files_imported',
                'name' => 'Scanner Files Imported',
                'subject' => '{{ count }} new files imported from scanner',
                'body' => '<h1>New files imported</h1><p>{{ count }} new files have been imported from your scanner and are being processed.</p><p><a href="{{ dashboard_url }}">View Files</a></p>',
                'variables' => json_encode(['count', 'dashboard_url']),
                'description' => 'Email sent when files are imported from scanner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'weekly_summary',
                'name' => 'Weekly Summary',
                'subject' => 'Your weekly receipt summary ({{ week_start }} - {{ week_end }})',
                'body' => '<h1>Weekly Summary</h1><p>Here\'s your weekly receipt summary for {{ week_start }} - {{ week_end }}:</p><p><strong>{{ total_receipts }}</strong> receipts processed<br><strong>{{ total_amount }}</strong> total spending<br><strong>{{ average_amount }}</strong> average per receipt</p>{{ categories_summary }}{{ merchants_summary }}<p><a href="{{ receipts_url }}">View All Receipts</a></p>',
                'variables' => json_encode(['week_start', 'week_end', 'total_receipts', 'total_amount', 'average_amount', 'categories_summary', 'merchants_summary', 'receipts_url']),
                'description' => 'Weekly summary email with receipt statistics',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'document_shared',
                'name' => 'Document Shared',
                'subject' => 'Document shared: {{ document_title }}',
                'body' => '<h1>Document shared with you</h1><p>{{ shared_by_name }} has shared a document with you.</p><p><strong>Document:</strong> {{ document_title }}<br><strong>Permission:</strong> {{ permission }}</p>{{ expires_info }}<p><a href="{{ document_url }}">View Document</a></p>',
                'variables' => json_encode(['shared_by_name', 'document_title', 'permission', 'expires_info', 'document_url']),
                'description' => 'Email sent when a document is shared',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'receipt_shared',
                'name' => 'Receipt Shared',
                'subject' => 'Receipt shared: {{ merchant_name }}',
                'body' => '<h1>Receipt shared with you</h1><p>{{ shared_by_name }} has shared a receipt with you.</p><p><strong>Merchant:</strong> {{ merchant_name }}<br><strong>Amount:</strong> {{ amount }} {{ currency }}<br><strong>Permission:</strong> {{ permission }}</p>{{ expires_info }}<p><a href="{{ receipt_url }}">View Receipt</a></p>',
                'variables' => json_encode(['shared_by_name', 'merchant_name', 'amount', 'currency', 'permission', 'expires_info', 'receipt_url']),
                'description' => 'Email sent when a receipt is shared',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
