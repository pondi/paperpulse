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
        Schema::table('user_preferences', function (Blueprint $table) {
            // In-app notification preferences
            $table->boolean('notify_processing_complete')->default(true);
            $table->boolean('notify_processing_failed')->default(true);
            $table->boolean('notify_bulk_complete')->default(true);
            $table->boolean('notify_scanner_import')->default(true);
            $table->boolean('notify_weekly_summary_ready')->default(true);
            
            // Email notification preferences (rename existing columns)
            $table->renameColumn('email_processing_complete', 'email_notify_processing_complete');
            $table->renameColumn('email_processing_failed', 'email_notify_processing_failed');
            
            // Add new email notification preferences
            $table->boolean('email_notify_bulk_complete')->default(false);
            $table->boolean('email_notify_scanner_import')->default(false);
            $table->boolean('email_notify_weekly_summary')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            // Remove in-app notification preferences
            $table->dropColumn([
                'notify_processing_complete',
                'notify_processing_failed',
                'notify_bulk_complete',
                'notify_scanner_import',
                'notify_weekly_summary_ready',
                'email_notify_bulk_complete',
                'email_notify_scanner_import',
                'email_notify_weekly_summary',
            ]);
            
            // Rename columns back
            $table->renameColumn('email_notify_processing_complete', 'email_processing_complete');
            $table->renameColumn('email_notify_processing_failed', 'email_processing_failed');
        });
    }
};