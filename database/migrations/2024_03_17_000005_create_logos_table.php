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
            $table->string('logoable_type');
            $table->unsignedBigInteger('logoable_id');
            $table->binary('logo_data');
            $table->string('mime_type');
            $table->string('hash');
            $table->timestamps();

            $table->index(['logoable_type', 'logoable_id']);
            $table->unique(['logoable_type', 'logoable_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logos');
    }
};
