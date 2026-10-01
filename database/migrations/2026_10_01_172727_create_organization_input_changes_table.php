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
        Schema::create('organization_input_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('change_key', 80);
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('revision');
            $table->unique(['user_id', 'change_key']);
            $table->index(['user_id', 'revision']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_input_changes');
    }
};
