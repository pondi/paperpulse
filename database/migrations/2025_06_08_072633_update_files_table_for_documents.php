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
        Schema::table('files', function (Blueprint $table) {
            $table->enum('file_type', ['receipt', 'document'])->default('receipt')->after('file_path');
            $table->string('s3_original_path')->nullable()->after('file_type');
            $table->string('s3_processed_path')->nullable()->after('s3_original_path');
            $table->enum('processing_type', ['receipt', 'document'])->default('receipt')->after('s3_processed_path');
            
            // Add indexes for better query performance
            $table->index('file_type');
            $table->index('processing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['file_type']);
            $table->dropIndex(['processing_type']);
            
            $table->dropColumn(['file_type', 's3_original_path', 's3_processed_path', 'processing_type']);
        });
    }
};