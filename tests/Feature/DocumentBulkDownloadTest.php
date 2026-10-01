<?php

use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Files\StoragePathBuilder;
use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;

it('downloads owned documents and skips unavailable assets', function (): void {
    Storage::fake('paperpulse');
    $user = User::factory()->create();
    $documents = collect(['first.pdf', 'missing.pdf', 'last.pdf'])->map(function (string $name) use ($user): Document {
        $file = File::factory()->create([
            'user_id' => $user->id,
            'file_type' => 'document',
            'fileExtension' => 'pdf',
            'fileName' => $name,
        ]);

        if ($name !== 'missing.pdf') {
            Storage::disk('paperpulse')->put(
                StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf'),
                '%PDF-'.$name
            );
        }

        return Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'A different document title']);
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
        expect($zip->getFromName('first.pdf'))->toBe('%PDF-first.pdf');
        expect($zip->getFromName('last.pdf'))->toBe('%PDF-last.pdf');
        expect($zip->locateName('missing.pdf'))->toBeFalse();
        $zip->close();
    } finally {
        unlink($zipPath);
    }
});

it('preserves safe uploaded filenames and resolves ZIP collisions', function (): void {
    Storage::fake('paperpulse');
    $user = User::factory()->create();
    $names = ['Årsrapport 2026.PDF', 'report.pdf', 'report_1.pdf', 'report.pdf', '../report.pdf', 'folder\\report.pdf', "report\n.pdf", 'extensionless'];
    $documents = collect($names)->map(function (string $name, int $index) use ($user): Document {
        $file = File::factory()->create([
            'user_id' => $user->id,
            'file_type' => 'document',
            'fileExtension' => 'pdf',
            'fileName' => $name,
        ]);
        Storage::disk('paperpulse')->put(
            StoragePathBuilder::storagePath($user->id, $file->guid, 'document', 'original', 'pdf'),
            '%PDF-'.$index
        );

        return Document::factory()->create(['user_id' => $user->id, 'file_id' => $file->id, 'title' => 'A different document title']);
    });

    $response = $this->actingAs($user)
        ->get(route('documents.download-bulk', ['ids' => $documents->pluck('id')->all()]))
        ->assertOk();
    $zipPath = tempnam(sys_get_temp_dir(), 'document-bulk-test');
    $zip = new ZipArchive;

    try {
        file_put_contents($zipPath, $response->streamedContent());
        expect($zip->open($zipPath))->toBeTrue()
            ->and($zip->numFiles)->toBe(count($names));

        foreach (['Årsrapport 2026.PDF', 'report.pdf', 'report_1.pdf', 'report_2.pdf', '.._report.pdf', 'folder_report.pdf', 'report_.pdf', 'extensionless.pdf'] as $index => $name) {
            expect($zip->getFromName($name))->toBe('%PDF-'.$index);
        }

        $zip->close();
    } finally {
        unlink($zipPath);
    }
});

it('downloads the uploaded filename instead of the entity title', function (string $model, string $route, string $name, string $expected): void {
    $entity = $model::factory()->create();
    $entity->file->update(['fileName' => $name, 'fileExtension' => 'pdf']);
    $this->mock(StorageService::class)
        ->shouldReceive('getFileByUserAndGuid')
        ->once()
        ->andReturn('%PDF-original');

    $this->actingAs($entity->user)
        ->get(route($route, $entity))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$expected.'"');
})->with([
    'document' => [Document::class, 'documents.download'],
    'invoice' => [Invoice::class, 'invoices.download'],
    'contract' => [Contract::class, 'contracts.download'],
    'voucher' => [Voucher::class, 'vouchers.download'],
])->with([
    'safe filename' => ['uploaded-report.pdf', 'uploaded-report.pdf'],
    'unsafe header characters' => ["report\"\r\n.pdf", 'report___.pdf'],
]);

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
