<?php

use App\Jobs\Files\ProcessFile;
use App\Jobs\Maintenance\DeleteWorkingFiles;
use App\Jobs\Receipts\MatchMerchant;
use App\Jobs\Receipts\ProcessReceipt;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('paperpulse');
    Storage::fake('pulsedav');
    Bus::fake();
});

it('uploads a receipt image and dispatches the receipt job chain', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $upload = UploadedFile::fake()->image('receipt.jpg', 600, 800);

    $response = $this->post(route('api.files.store'), [
        'file' => $upload,
        'file_type' => 'receipt',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('message', 'File uploaded for processing');

    $file = File::query()->where('user_id', $user->id)->first();
    expect($file)->not->toBeNull();
    expect($file->file_type)->toBe('receipt');
    expect($file->fileName)->toBe('receipt.jpg');

    Bus::assertChained([
        fn (ProcessFile $job) => true,
        fn (ProcessReceipt $job) => true,
        fn (MatchMerchant $job) => true,
        fn (DeleteWorkingFiles $job) => true,
    ]);
});

it('uploads a receipt PDF and dispatches the receipt job chain', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $upload = UploadedFile::fake()
        ->createWithContent('receipt.pdf', '%PDF-1.4 test')
        ->mimeType('application/pdf');

    $response = $this->post(route('api.files.store'), [
        'file' => $upload,
        'file_type' => 'receipt',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('message', 'File uploaded for processing');

    $file = File::query()->where('user_id', $user->id)->first();
    expect($file)->not->toBeNull();
    expect($file->file_type)->toBe('receipt');
    expect($file->fileName)->toBe('receipt.pdf');
    expect($file->fileExtension)->toBe('pdf');

    Bus::assertChained([
        fn (ProcessFile $job) => true,
        fn (ProcessReceipt $job) => true,
        fn (MatchMerchant $job) => true,
        fn (DeleteWorkingFiles $job) => true,
    ]);
});

it('returns 409 when uploading a duplicate file for the same user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $content = 'same-bytes-every-time';

    $first = UploadedFile::fake()
        ->createWithContent('duplicate.jpg', $content)
        ->mimeType('image/jpeg');

    $firstResponse = $this->post(route('api.files.store'), [
        'file' => $first,
        'file_type' => 'receipt',
    ]);

    $firstResponse->assertStatus(201);

    $firstFile = File::query()->where('user_id', $user->id)->first();
    expect($firstFile)->not->toBeNull();

    $second = UploadedFile::fake()
        ->createWithContent('duplicate.jpg', $content)
        ->mimeType('image/jpeg');

    $secondResponse = $this->post(route('api.files.store'), [
        'file' => $second,
        'file_type' => 'receipt',
    ]);

    $secondResponse->assertStatus(409);
    $secondResponse->assertJsonPath('message', 'Duplicate file detected');
    $secondResponse->assertJsonPath('errors.duplicate.existing_file.id', $firstFile->id);

    expect(File::query()->where('user_id', $user->id)->count())->toBe(1);
});
