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
        Schema::create('duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('file_id');
            $table->unsignedBigInteger('duplicate_file_id');
            $table->string('reason', 150);
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('resolved_file_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'file_id', 'duplicate_file_id']);
            $table->index(['user_id', 'status']);
            $table->index(['file_id', 'duplicate_file_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_flags');
    }
};
