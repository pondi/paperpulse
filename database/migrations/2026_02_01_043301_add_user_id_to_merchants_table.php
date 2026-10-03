<?php

use App\Services\TenantReferenceBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });

        app(TenantReferenceBackfill::class)->run('merchants');

        Schema::table('merchants', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'name'], 'merchants_user_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropUnique('merchants_user_id_name_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
