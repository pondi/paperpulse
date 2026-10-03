<?php

use App\Models\Document;
use App\Models\File;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Populated upgrades require the PostgreSQL test database.');
    }
});

it('upgrades shared tenant references and orphan receipts without losing relationships', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $merchant = Merchant::create(['user_id' => $first->id, 'name' => 'Shared store']);
    $duplicate = Merchant::create(['user_id' => $second->id, 'name' => 'Shared store']);
    $vendor = Vendor::create(['user_id' => $first->id, 'name' => 'Shared vendor']);
    $unused = Merchant::create(['user_id' => $first->id, 'name' => 'Unused merchant']);
    $a = Receipt::factory()->create(['user_id' => $first->id, 'merchant_id' => $merchant->id]);
    $b = Receipt::factory()->create(['user_id' => $second->id, 'merchant_id' => $merchant->id]);
    $c = Receipt::factory()->create(['user_id' => $first->id, 'merchant_id' => $duplicate->id]);
    $orphan = Receipt::factory()->create(['user_id' => null, 'merchant_id' => $merchant->id]);
    $items = collect([$a, $b, $orphan])->map(fn ($receipt) => LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id]));
    $merchantMigration = require database_path('migrations/2026_02_01_043301_add_user_id_to_merchants_table.php');
    $vendorMigration = require database_path('migrations/2026_02_01_043735_add_user_id_to_vendors_table.php');
    $merchantMigration->down();
    $vendorMigration->down();
    $merchantMigration->up();
    $vendorMigration->up();

    expect($a->fresh()->merchant_id)->toBe($c->fresh()->merchant_id);
    expect($a->fresh()->merchant_id)->not->toBe($b->fresh()->merchant_id);
    foreach ([$a, $b, $c] as $receipt) {
        expect(DB::table('merchants')->where('id', $receipt->fresh()->merchant_id)->value('user_id'))->toBe($receipt->user_id);
    }
    expect($orphan->fresh()->merchant_id)->toBeNull();
    expect(DB::table('merchants')->where('id', $unused->id)->exists())->toBeFalse();
    expect($items[2]->fresh()->vendor_id)->toBeNull();
    foreach ($items->take(2) as $item) {
        expect(DB::table('vendors')->where('id', $item->fresh()->vendor_id)->value('user_id'))->toBe($item->receipt->user_id);
    }
});

it('migrates a large pivot in SQL and preserves deduplicated owned file memberships', function (): void {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $user->id]);
    $foreignTag = Tag::factory()->create();
    $receipts = Receipt::factory()->count(501)->create(['user_id' => $user->id, 'file_id' => File::factory()->state(['user_id' => $user->id])]);
    $document = Document::factory()->create(['user_id' => $user->id, 'file_id' => $receipts[0]->file_id]);
    Schema::drop('file_tags');
    (require database_path('migrations/2024_03_17_000011_create_file_tags_table.php'))->up();
    DB::table('file_tags')->insert($receipts->map(fn ($receipt) => [
        'file_id' => $receipt->id, 'file_type' => 'receipt', 'tag_id' => $tag->id,
        'created_at' => now(), 'updated_at' => now(),
    ])->all());
    DB::table('file_tags')->insert([
        ['file_id' => $document->id, 'file_type' => 'document', 'tag_id' => $tag->id],
        ['file_id' => 999999, 'file_type' => 'receipt', 'tag_id' => $tag->id],
        ['file_id' => $receipts[0]->id, 'file_type' => 'receipt', 'tag_id' => $foreignTag->id],
    ]);
    $reads = [];
    DB::listen(function (QueryExecuted $query) use (&$reads): void {
        if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'file_tags')) {
            $reads[] = $query->sql;
        }
    });
    $migration = require database_path('migrations/2026_02_01_044449_move_tags_to_file_model.php');
    $migration->up();
    expect($reads)->toBe([]);
    expect(DB::table('file_tags')->count())->toBe(501);
    expect(DB::table('file_tags')->where('tag_id', $tag->id)->pluck('file_id')->sort()->values()->all())
        ->toBe($receipts->pluck('file_id')->sort()->values()->all());
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'backup');
});

it('repairs already deployed foreign tenant references and is idempotent', function (): void {
    $owner = User::factory()->create();
    $merchant = Merchant::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign merchant']);
    $vendor = Vendor::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign vendor']);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'merchant_id' => $merchant->id]);
    $item = LineItem::create(['receipt_id' => $receipt->id, 'vendor_id' => $vendor->id]);
    $path = glob(database_path('migrations/*repair_tenant_reference_ownership.php'))[0];
    $repair = require $path;
    $repair->up();
    $repair->up();
    expect(DB::table('merchants')->where('id', $receipt->fresh()->merchant_id)->value('user_id'))->toBe($owner->id);
    expect(DB::table('vendors')->where('id', $item->fresh()->vendor_id)->value('user_id'))->toBe($owner->id);
    expect(DB::table('merchants')->where('user_id', $owner->id)->where('name', $merchant->name)->count())->toBe(1);
    expect(DB::table('vendors')->where('user_id', $owner->id)->where('name', $vendor->name)->count())->toBe(1);
});
