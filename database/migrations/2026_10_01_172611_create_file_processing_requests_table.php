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
        Schema::create('file_processing_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('file_id')->index();
            $table->string('file_type');
            $table->string('guid');
            $table->string('extension');
            $table->string('storage_disk');
            $table->text('original_path');
            $table->text('working_path')->nullable();
            $table->string('state')->default('upload_pending')->index();
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_processing_requests');
    }
};
