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
        Schema::create('pulsedav_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('s3_path');
            $table->string('filename');
            $table->bigInteger('size')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('uploaded_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('receipt_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('s3_path');
            $table->index('uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulsedav_files');
    }
};
