<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('fileName');
            $table->string('fileExtension');
            $table->string('fileType');
            $table->bigInteger('fileSize');
            $table->binary('fileImage')->nullable();
            $table->string('guid')->unique();
            $table->string('file_type')->default('receipt'); // receipt, document, etc
            $table->string('processing_type')->default('receipt');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('s3_original_path')->nullable();
            $table->string('s3_processed_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['user_id', 'file_type']);
            $table->index(['status', 'created_at']);
            $table->index('guid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
