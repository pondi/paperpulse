<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_upload_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bulk_upload_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('original_path')->nullable();
            $table->bigInteger('file_size');
            $table->string('file_hash', 64);
            $table->string('file_extension', 20);
            $table->string('mime_type');
            $table->string('status')->default('pending');
            $table->string('file_type')->nullable();
            $table->json('collection_ids')->nullable();
            $table->json('tag_ids')->nullable();
            $table->text('note')->nullable();
            $table->string('s3_key')->nullable();
            $table->timestamp('presigned_expires_at')->nullable();
            $table->string('job_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['bulk_upload_session_id', 'status']);
            $table->index(['user_id', 'file_hash']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_upload_files');
    }
};
