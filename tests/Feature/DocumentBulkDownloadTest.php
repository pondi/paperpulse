<?php

use App\Models\Document;
use App\Models\File;
use App\Models\User;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;

it('downloads owned documents and skips unavailable assets', function (): void {
    Storage::fake('paperpulse');
    $user = User::factory()->create();
    $documents = collect(['first', 'missing', 'last'])->map(function (string $name) use ($user): Document {
        $file = File::factory()->create([
            'user_id' => $user->id,
            'file_type' => 'document',
            'fileExtension' => 'pdf',
            'fileName' => $name,
        ]);

        if ($name !== 'missing') {
            Storage::disk('paperpulse')->put(
                StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf'),
                '%PDF-'.$name
            );
        }

        return Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => $name]);
    });

    $response = $this->actingAs($user)
        ->get(route('documents.download-bulk', ['ids' => $documents->pluck('id')->all()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    $zipPath = tempnam(sys_get_temp_dir(), 'document-bulk-test');
    $zip = new ZipArchive;

    try {
        file_put_contents($zipPath, $response->streamedContent());
        expect($zip->open($zipPath))->toBeTrue();
        expect($zip->numFiles)->toBe(2);
        expect($zip->getFromName('first.pdf'))->toBe('%PDF-first');
        expect($zip->getFromName('last.pdf'))->toBe('%PDF-last');
        expect($zip->locateName('missing.pdf'))->toBeFalse();
        $zip->close();
    } finally {
        unlink($zipPath);
    }
});

it('rejects invalid document selections before reading storage', function (string $selection): void {
    $owned = Document::factory()->create();
    $user = $owned->user;
    $foreign = Document::factory()->create();
    $ids = match ($selection) {
        'foreign' => [$foreign->id],
        'mixed' => [$owned->id, $foreign->id],
        'nonexistent' => [$foreign->id + 1],
        'empty' => [],
    };
    $this->mock(StorageService::class)->shouldNotReceive('getFileByUserAndGuid');

    $this->actingAs($user)
        ->getJson(route('documents.download-bulk', ['ids' => $ids]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($selection === 'empty' ? 'ids' : 'ids.'.($selection === 'mixed' ? '1' : '0'));
})->with(['foreign', 'mixed', 'nonexistent', 'empty']);
