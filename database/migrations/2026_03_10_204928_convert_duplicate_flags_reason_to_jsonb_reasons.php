<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duplicate_flags', function (Blueprint $table) {
            $table->jsonb('reasons')->default('[]')->after('duplicate_file_id');
        });

        DB::table('duplicate_flags')->orderBy('id')->each(function (object $flag): void {
            $reasons = array_values(array_filter(explode('|', $flag->reason ?? '')));

            DB::table('duplicate_flags')
                ->where('id', $flag->id)
                ->update(['reasons' => json_encode($reasons)]);
        });

        Schema::table('duplicate_flags', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }

    public function down(): void
    {
        Schema::table('duplicate_flags', function (Blueprint $table) {
            $table->string('reason', 150)->after('duplicate_file_id');
        });

        DB::table('duplicate_flags')->orderBy('id')->each(function (object $flag): void {
            $reasons = json_decode($flag->reasons, true) ?? [];
            $reason = mb_substr(implode('|', $reasons), 0, 150);

            DB::table('duplicate_flags')
                ->where('id', $flag->id)
                ->update(['reason' => $reason]);
        });

        Schema::table('duplicate_flags', function (Blueprint $table) {
            $table->dropColumn('reasons');
        });
    }
};
