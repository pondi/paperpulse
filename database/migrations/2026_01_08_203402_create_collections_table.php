<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->default('folder');
            $table->string('color')->default('#3B82F6');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
