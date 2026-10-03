<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TenantReferenceBackfill
{
    public function run(string $table): void
    {
        $foreignKey = $table === 'merchants' ? 'merchant_id' : 'vendor_id';
        $lastId = (int) DB::table($table)->max('id');
        foreach (DB::table($table)->where('id', '<=', $lastId)->lazyById(200) as $entity) {
            DB::transaction(function () use ($table, $foreignKey, $entity): void {
                $owners = $table === 'merchants'
                    ? DB::table('receipts')->where($foreignKey, $entity->id)
                    : DB::table('line_items')->join('receipts', 'receipts.id', '=', 'line_items.receipt_id')->where($foreignKey, $entity->id);
                foreach ($owners->select('receipts.user_id')->whereNotNull('receipts.user_id')->distinct()->lazyById(200, 'receipts.user_id', 'user_id') as $owner) {
                    if ($entity->user_id === $owner->user_id) {
                        continue;
                    }
                    $owned = DB::table($table)->where('user_id', $owner->user_id)->where('name', $entity->name)->value('id');
                    if ($owned === null && $entity->user_id === null) {
                        DB::table($table)->where('id', $entity->id)->update(['user_id' => $owner->user_id]);
                        $entity->user_id = $owner->user_id;

                        continue;
                    }
                    if ($owned === null) {
                        $copy = (array) $entity;
                        unset($copy['id']);
                        $copy['user_id'] = $owner->user_id;
                        $owned = DB::table($table)->insertGetId($copy);
                    }
                    $this->references($table, $entity->id, $owner->user_id)->update([$foreignKey => $owned]);
                }
                $this->references($table, $entity->id, null)->update([$foreignKey => null]);
                if ($entity->user_id === null) {
                    DB::table($table)->where('id', $entity->id)->delete();
                }
            });
        }
    }

    private function references(string $table, int $id, ?int $userId): Builder
    {
        return $table === 'merchants'
            ? DB::table('receipts')->where('merchant_id', $id)->where('user_id', $userId)
            : DB::table('line_items')->where('vendor_id', $id)->whereIn('receipt_id', DB::table('receipts')->select('id')->where('user_id', $userId));
    }
}
