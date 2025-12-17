<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_shares', function (Blueprint $table) {
            if (! Schema::hasColumn('file_shares', 'share_token')) {
                $table->string('share_token', 32)->nullable()->after('permission');
                $table->unique('share_token');
            }

            if (! Schema::hasColumn('file_shares', 'accessed_at')) {
                $table->timestamp('accessed_at')->nullable()->after('expires_at');
            }
        });

        // Backfill share tokens for existing rows, keeping uniqueness.
        $missingTokens = DB::table('file_shares')->whereNull('share_token')->pluck('id');
        foreach ($missingTokens as $id) {
            do {
                $token = Str::random(32);
            } while (DB::table('file_shares')->where('share_token', $token)->exists());

            DB::table('file_shares')->where('id', $id)->update(['share_token' => $token]);
        }
    }

    public function down(): void
    {
        Schema::table('file_shares', function (Blueprint $table) {
            if (Schema::hasColumn('file_shares', 'accessed_at')) {
                $table->dropColumn('accessed_at');
            }

            if (Schema::hasColumn('file_shares', 'share_token')) {
                $table->dropUnique(['share_token']);
                $table->dropColumn('share_token');
            }
        });
    }
};

