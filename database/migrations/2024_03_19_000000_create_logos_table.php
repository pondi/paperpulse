<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logos', function (Blueprint $table) {
            $table->id();
            $table->morphs('logoable');  // Creates logoable_id and logoable_type columns
            $table->binary('logo_data');
            $table->string('mime_type');
            $table->string('hash')->unique()->index();  // For deduplication
            $table->timestamps();

            // Add a unique constraint to prevent duplicate logos for the same entity
            $table->unique(['logoable_type', 'logoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logos');
    }
};
