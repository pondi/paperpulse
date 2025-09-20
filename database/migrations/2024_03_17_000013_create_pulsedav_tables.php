<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulsedav_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('imported_at')->useCurrent();
            $table->integer('file_count')->default(0);
            $table->json('tag_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('imported_at');
        });

        Schema::create('pulsedav_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('pulsedav_import_batches')->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('s3_path');
            $table->string('filename');
            $table->bigInteger('size')->nullable();
            $table->string('file_type')->default('receipt');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('folder_path')->nullable();
            $table->string('parent_folder')->nullable();
            $table->integer('depth')->default(0);
            $table->boolean('is_folder')->default(false);
            $table->json('folder_tag_ids')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_folder']);
            $table->index('folder_path');
            $table->index('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulsedav_files');
        Schema::dropIfExists('pulsedav_import_batches');
    }
};
