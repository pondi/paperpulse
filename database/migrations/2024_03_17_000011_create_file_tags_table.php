<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->string('file_type'); // receipt, document
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['file_id', 'file_type', 'tag_id']);
            $table->index(['file_id', 'file_type']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_tags');
    }
};
