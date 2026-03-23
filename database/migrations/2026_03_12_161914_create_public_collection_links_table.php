<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_collection_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('label')->nullable();
            $table->string('password_hash')->nullable();
            $table->boolean('is_password_protected')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_views')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'is_active']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_collection_links');
    }
};
