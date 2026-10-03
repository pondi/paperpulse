<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\File;
use App\Models\LineItem;
use App\Models\Merchant;
use App\Models\Receipt;
use App\Models\User;

it('exports spreadsheet-safe text while preserving numeric and date cells', function (string $export) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $merchant = Merchant::create([
        'user_id' => $user->id,
        'name' => '=SUM(1,2),"quoted"\\',
    ]);
    $receipt = Receipt::factory()->create([
        'user_id' => $user->id,
        'file_id' => File::factory()->create(['user_id' => $user->id])->id,
        'merchant_id' => $merchant->id,
        'receipt_date' => '2025-06-15',
        'category_id' => Category::create(['user_id' => $user->id, 'name' => '+Category', 'slug' => 'category'])->id,
        'receipt_category' => '+Category',
        'receipt_description' => " \t@Description",
        'total_amount' => -12.50,
        'tax_amount' => -2.50,
        'currency' => 'EUR',
    ]);
    $receipt->file->update(['note' => "\r=Note"]);
    LineItem::create([
        'receipt_id' => $receipt->id,
        'text' => '-Item',
        'qty' => 1,
        'price' => -12.50,
    ]);

    if ($export === 'bulk') {
        $response = $this->post(route('bulk.receipts.export.csv'), ['receipt_ids' => [$receipt->id]]);
    } else {
        $response = $this->get(route('export.receipts.csv', ['merchant_id' => $merchant->id]));
    }

    $response->assertOk();
    $csv = fopen('php://temp', 'w+');
    fwrite($csv, $response->streamedContent());
    rewind($csv);
    $headers = fgetcsv($csv, escape: '');
    $row = array_combine($headers, fgetcsv($csv, escape: ''));
    expect(fgetcsv($csv, escape: ''))->toBeFalse();
    fclose($csv);

    expect($row['Merchant'])->toBe("'".$merchant->name)
        ->and($row['Category'])->toBe("'+Category")
        ->and($row['Description'])->toBe("' \t@Description")
        ->and($row['Line Items'])->toBe("'-Item (Qty: 1, Price: -12.5)")
        ->and($row['Total Amount'])->toBe('-12.50')
        ->and($row['Tax Amount'])->toBe('-2.50')
        ->and($row['Receipt Date'])->toBe('2025-06-15')
        ->and($row['Currency'])->toBe('EUR')
        ->and($row['Items Count'])->toBe('1');

    if ($export === 'filtered') {
        expect($row['Note'])->toBe("'\r=Note");
    }
})->with(['filtered', 'bulk']);
