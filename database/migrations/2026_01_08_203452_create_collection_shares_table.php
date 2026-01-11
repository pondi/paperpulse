<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission')->default('view');
            $table->string('share_token', 32)->nullable()->unique();
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['collection_id', 'shared_with_user_id']);
            $table->index('shared_with_user_id');
            $table->index('shared_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_shares');
    }
};
