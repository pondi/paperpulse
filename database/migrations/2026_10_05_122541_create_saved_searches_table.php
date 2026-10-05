<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('scope', 20)->default('library');
            $table->jsonb('filters');
            $table->boolean('is_pinned')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'is_pinned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
