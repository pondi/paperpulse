<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('input_revision');
            $table->string('input_fingerprint', 64);
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedSmallInteger('calls')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('organization_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_run_id')->constrained()->cascadeOnDelete();
            $table->json('operation');
            $table->json('before_state');
            $table->json('after_state')->nullable();
            $table->string('signature', 64);
            $table->string('status')->default('pending');
            $table->float('confidence');
            $table->string('reason', 240);
            $table->string('decision_reason', 240)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_run_id', 'signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_recommendations');
        Schema::dropIfExists('organization_runs');
    }
};
