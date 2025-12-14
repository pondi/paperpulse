<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->text('note')->nullable()->after('receipt_description');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->text('note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};

