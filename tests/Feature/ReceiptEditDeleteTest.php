<?php

use App\Models\File;
use App\Models\Receipt;
use App\Models\User;

test('owners can edit and delete receipts through the web routes', function () {
    $owner = User::factory()->create();
    $file = File::factory()->create(['user_id' => $owner->id]);
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'file_id' => $file->id]);
    $this->actingAs($owner)->patch('/receipts/'.$receipt->id, [
        'receipt_date' => '2026-01-02', 'total_amount' => '25.50', 'currency' => 'NOK', 'note' => 'Edited',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($receipt->fresh()->total_amount)->toBe('25.50')->and($receipt->fresh()->note)->toBe('Edited');
    $this->delete('/receipts/'.$receipt->id)->assertRedirect(route('receipts.index'));
    $this->assertSoftDeleted('receipts', ['id' => $receipt->id]);
    $this->assertSoftDeleted('files', ['id' => $file->id]);
});

test('foreign receipt edits and deletes are rejected without modifying records', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($stranger)->patch('/receipts/'.$receipt->id, [
        'receipt_date' => '2026-01-02', 'total_amount' => '25.50', 'currency' => 'NOK',
    ])->assertNotFound();
    $this->delete('/receipts/'.$receipt->id)->assertNotFound();
    $this->assertDatabaseHas('receipts', ['id' => $receipt->id, 'deleted_at' => null]);
});

test('invalid receipt edits return validation errors and retain existing data', function () {
    $owner = User::factory()->create();
    $receipt = Receipt::factory()->create(['user_id' => $owner->id, 'total_amount' => 10, 'file_id' => File::factory()->create(['user_id' => $owner->id])->id]);
    $this->actingAs($owner)->patch('/receipts/'.$receipt->id, [
        'receipt_date' => 'bad', 'total_amount' => 'bad', 'currency' => 'NOK',
    ])->assertSessionHasErrors(['receipt_date', 'total_amount']);
    expect($receipt->fresh()->total_amount)->toBe('10.00');
});
