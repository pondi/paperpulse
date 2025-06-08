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
        Schema::create('file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->onDelete('cascade');
            $table->enum('file_type', ['receipt', 'document']);
            $table->foreignId('shared_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shared_with_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('permission', ['view', 'edit'])->default('view');
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            // Indexes for performance
            $table->index(['file_id', 'file_type']);
            $table->index('shared_by_user_id');
            $table->index('shared_with_user_id');
            $table->index('expires_at');
            
            // Unique constraint to prevent duplicate shares
            $table->unique(['file_id', 'file_type', 'shared_with_user_id'], 'unique_file_share');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_shares');
    }
};