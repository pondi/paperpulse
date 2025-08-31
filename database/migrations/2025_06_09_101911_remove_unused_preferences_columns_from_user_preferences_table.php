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
            $table->dropColumn([
                'ocr_handwritten',
                'analytics_enabled',
                'share_usage_data',
                'show_receipt_preview'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('ocr_handwritten')->default(false);
            $table->boolean('analytics_enabled')->default(true);
            $table->boolean('share_usage_data')->default(false);
            $table->boolean('show_receipt_preview')->default(true);
        });
    }
};
