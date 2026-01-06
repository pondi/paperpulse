<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('notified_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'notification_type', 'entity_type', 'entity_id'],
                'notification_history_unique'
            );
            $table->index(['user_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_history');
    }
};
