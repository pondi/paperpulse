<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            // General preferences
            $table->string('language', 5)->default('en');
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->string('currency')->default('NOK');

            // Receipt processing preferences
            $table->boolean('auto_categorize')->default(true);
            $table->boolean('extract_line_items')->default(true);
            $table->boolean('ocr_handwritten')->default(false);
            $table->string('default_category_id')->nullable();

            // Notification preferences
            $table->boolean('email_processing_complete')->default(true);
            $table->boolean('email_processing_failed')->default(true);
            $table->boolean('email_weekly_summary')->default(false);
            $table->string('weekly_summary_day')->default('monday');

            // Display preferences
            $table->string('receipt_list_view')->default('grid'); // grid or list
            $table->integer('receipts_per_page')->default(20);
            $table->string('default_sort')->default('date_desc');
            $table->boolean('show_receipt_preview')->default(true);

            // Scanner/Import preferences
            $table->boolean('auto_process_scanner_uploads')->default(false);
            $table->boolean('delete_after_processing')->default(false);
            $table->integer('file_retention_days')->default(30);

            // Privacy preferences
            $table->boolean('analytics_enabled')->default(true);
            $table->boolean('share_usage_data')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
