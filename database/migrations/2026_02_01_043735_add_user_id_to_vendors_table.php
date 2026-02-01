<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Vendors were previously shared globally (privacy issue).
     * This migration makes them user-specific by:
     * 1. Adding user_id column
     * 2. Duplicating vendors for each user that references them via line_items
     * 3. Updating line_item references to point to user-specific vendors
     */
    public function up(): void
    {
        // Step 1: Add nullable user_id column
        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Step 2: For each vendor, find all users who have line_items with this vendor
        // and create user-specific copies
        $vendors = DB::table('vendors')->whereNull('user_id')->get();

        foreach ($vendors as $vendor) {
            // Find all unique users who have line_items with this vendor (via receipts)
            $userIds = DB::table('line_items')
                ->join('receipts', 'line_items.receipt_id', '=', 'receipts.id')
                ->where('line_items.vendor_id', $vendor->id)
                ->distinct()
                ->pluck('receipts.user_id');

            if ($userIds->isEmpty()) {
                // No line_items reference this vendor - delete it
                DB::table('vendors')->where('id', $vendor->id)->delete();

                continue;
            }

            // First user keeps the original vendor record
            $firstUserId = $userIds->shift();
            DB::table('vendors')
                ->where('id', $vendor->id)
                ->update(['user_id' => $firstUserId]);

            // Create copies for additional users
            foreach ($userIds as $userId) {
                $newVendorId = DB::table('vendors')->insertGetId([
                    'user_id' => $userId,
                    'name' => $vendor->name,
                    'logo_path' => $vendor->logo_path,
                    'website' => $vendor->website,
                    'contact_email' => $vendor->contact_email,
                    'contact_phone' => $vendor->contact_phone,
                    'description' => $vendor->description,
                    'created_at' => $vendor->created_at,
                    'updated_at' => now(),
                ]);

                // Update this user's line_items to point to their copy
                // Get receipt IDs for this user
                $receiptIds = DB::table('receipts')
                    ->where('user_id', $userId)
                    ->pluck('id');

                DB::table('line_items')
                    ->where('vendor_id', $vendor->id)
                    ->whereIn('receipt_id', $receiptIds)
                    ->update(['vendor_id' => $newVendorId]);
            }
        }

        // Step 3: Make user_id non-nullable
        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // Step 4: Add unique constraint on user_id + name
        Schema::table('vendors', function (Blueprint $table) {
            $table->unique(['user_id', 'name'], 'vendors_user_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique('vendors_user_id_name_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
