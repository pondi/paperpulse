<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entity type to table mapping.
     * file_type value => [table_name, file_id_column]
     */
    private array $entityTypeMap = [
        'receipt' => ['receipts', 'file_id'],
        'document' => ['documents', 'file_id'],
        'voucher' => ['vouchers', 'file_id'],
        'warranty' => ['warranties', 'file_id'],
        'return_policy' => ['return_policies', 'file_id'],
        'invoice' => ['invoices', 'file_id'],
        'contract' => ['contracts', 'file_id'],
        'bank_statement' => ['bank_statements', 'file_id'],
    ];

    public function up(): void
    {
        Schema::create('file_tags_migrated', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['file_id', 'tag_id']);
            $table->index('tag_id');
        });

        foreach ($this->entityTypeMap as $fileType => [$table, $fileIdColumn]) {
            $membership = DB::table('file_tags as pivot')
                ->join($table.' as entity', 'entity.id', '=', 'pivot.file_id')
                ->join('files', 'files.id', '=', 'entity.'.$fileIdColumn)
                ->join('tags', 'tags.id', '=', 'pivot.tag_id')
                ->where('pivot.file_type', $fileType)
                ->whereColumn('files.user_id', 'tags.user_id')
                ->select('files.id as file_id', 'pivot.tag_id')
                ->selectRaw('MIN(pivot.created_at) as created_at, MIN(pivot.updated_at) as updated_at')
                ->groupBy('files.id', 'pivot.tag_id');
            DB::table('file_tags_migrated')->insertOrIgnoreUsing(['file_id', 'tag_id', 'created_at', 'updated_at'], $membership);
        }

        Schema::drop('file_tags');
        Schema::rename('file_tags_migrated', 'file_tags');
    }

    public function down(): void
    {
        throw new RuntimeException('Tag migration merges entity memberships. Restore a pre-upgrade database backup instead of rolling back.');
    }
};
