<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_share_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_collection_link_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->timestamp('accessed_at');

            $table->index(['public_collection_link_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_share_access_logs');
    }
};
