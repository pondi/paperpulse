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
        Schema::create('file_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending'); // pending, processing, completed, failed

            $table->string('input_extension', 10); // docx, xlsx, pptx, etc.
            $table->string('input_s3_path'); // Original office file path
            $table->string('output_s3_path')->nullable(); // Converted PDF path

            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);

            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Extra data (worker_id, duration, etc.)

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['file_id', 'status'], 'idx_file_status');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('user_id', 'idx_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_conversions');
    }
};
