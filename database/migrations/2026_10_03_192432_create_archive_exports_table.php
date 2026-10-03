<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->string('status')->default('pending');
            $table->json('filters');
            $table->unsignedInteger('total');
            $table->unsignedInteger('processed')->default(0);
            $table->string('path')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_exports');
    }
};
