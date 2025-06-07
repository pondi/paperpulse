<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_histories', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->index(); // The jobID from your job classes
            $table->string('job_uuid')->nullable()->index(); // Laravel's job UUID
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

    public function down(): void
    {
        Schema::dropIfExists('job_histories');
    }
}; 