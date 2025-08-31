<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language')->default('en');
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->string('currency')->default('NOK');
            $table->boolean('auto_categorize')->default(true);
            $table->boolean('extract_line_items')->default(true);
            $table->string('default_category_id')->nullable();

            // Email notifications
            $table->boolean('email_notify_processing_complete')->default(true);
            $table->boolean('email_notify_processing_failed')->default(true);
            $table->boolean('email_notify_bulk_complete')->default(false);
            $table->boolean('email_notify_scanner_import')->default(false);
            $table->boolean('email_weekly_summary')->default(false);

            // In-app notifications
            $table->boolean('notify_processing_complete')->default(true);
            $table->boolean('notify_processing_failed')->default(true);
            $table->boolean('notify_bulk_complete')->default(true);
            $table->boolean('notify_scanner_import')->default(true);
            $table->boolean('notify_weekly_summary_ready')->default(true);

            $table->string('weekly_summary_day')->default('monday');
            $table->string('receipt_list_view')->default('grid');
            $table->integer('receipts_per_page')->default(20);
            $table->string('default_sort')->default('date_desc');
            $table->boolean('auto_process_scanner_uploads')->default(false);
            $table->boolean('delete_after_processing')->default(false);
            $table->integer('file_retention_days')->default(30);
            $table->boolean('pulsedav_realtime_sync')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
