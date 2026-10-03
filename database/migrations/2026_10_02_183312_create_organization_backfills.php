<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_backfills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedBigInteger('max_file_id');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('extract_missing')->default(false);
            $table->unsignedInteger('max_calls')->default(10);
            $table->unsignedInteger('max_tokens')->default(160000);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_backfills');
    }
};
