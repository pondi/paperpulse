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
        Schema::create('batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_job_id')->constrained()->onDelete('cascade');
            $table->integer('item_index');
            $table->text('source'); // file path, URL, or content identifier
            $table->string('type'); // receipt, document, etc.
            $table->json('options')->nullable();
            $table->string('status'); // queued, processing, completed, failed
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('processing_time')->default(0); // milliseconds
            $table->decimal('cost', 8, 4)->default(0);
            $table->integer('retries')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_job_id', 'status']);
            $table->index(['batch_job_id', 'item_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_items');
    }
};
