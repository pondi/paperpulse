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
        Schema::create('file_processing_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Processing metadata
            $table->string('processing_type')->default('gemini'); // gemini, textract+openai, etc.
            $table->string('processing_status'); // completed, failed
            $table->integer('processing_duration_ms')->nullable();
            $table->string('model_used')->nullable();

            // Classification data (Pass 1)
            $table->string('document_type')->nullable(); // receipt, invoice, voucher, etc.
            $table->decimal('classification_confidence', 5, 4)->nullable(); // 0.0000 to 1.0000
            $table->text('classification_reasoning')->nullable();
            $table->json('detected_entities')->nullable();

            // Extraction data (Pass 2)
            $table->decimal('extraction_confidence', 5, 4)->nullable(); // 0.0000 to 1.0000
            $table->json('validation_warnings')->nullable();

            // Failure tracking
            $table->string('failure_category')->nullable(); // classification_low_confidence, extraction_validation_failed, etc.
            $table->text('error_message')->nullable();
            $table->boolean('is_retryable')->nullable();

            $table->timestamps();

            // Indexes for analytics queries
            $table->index('document_type');
            $table->index('classification_confidence');
            $table->index('extraction_confidence');
            $table->index('failure_category');
            $table->index('processing_status');
            $table->index(['user_id', 'document_type']);
            $table->index(['user_id', 'processing_status']);
            $table->index(['document_type', 'processing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_processing_analytics');
    }
};
