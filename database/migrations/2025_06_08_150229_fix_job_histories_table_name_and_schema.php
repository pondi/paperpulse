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
        // First, drop the existing table if it exists
        Schema::dropIfExists('job_histories');
        
        // Create the table with the correct name and schema
        Schema::create('job_history', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->index();
            $table->string('parent_uuid')->nullable()->index();
            $table->string('name');
            $table->string('queue');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->integer('attempt')->default(0);
            $table->integer('progress')->default(0);
            $table->integer('order_in_chain')->default(0);
            $table->text('exception')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            
            // Add foreign key constraint for parent_uuid
            $table->index(['parent_uuid', 'order_in_chain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_history');
        
        // Recreate the old table structure
        Schema::create('job_histories', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->index();
            $table->string('job_uuid')->nullable()->index();
            $table->string('name');
            $table->string('status');
            $table->string('queue');
            $table->json('payload')->nullable();
            $table->text('exception')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
};
