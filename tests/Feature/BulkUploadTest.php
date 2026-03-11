<?php

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Models\BulkUploadFile;
use App\Models\BulkUploadSession;
use App\Models\File;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

// --- Session Creation ---

it('creates a bulk upload session with file manifest', function () {
    $response = $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'receipt',
        'note' => 'Test batch',
        'files' => [
            [
                'filename' => 'receipt_001.pdf',
                'size' => 245000,
                'hash' => 'sha256:'.hash('sha256', 'file1content'),
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
            ],
            [
                'filename' => 'receipt_002.jpg',
                'size' => 120000,
                'hash' => hash('sha256', 'file2content'),
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total_files', 2)
        ->assertJsonPath('data.uploadable_files', 2)
        ->assertJsonPath('data.duplicate_files', 0)
        ->assertJsonStructure([
            'data' => [
                'session_id',
                'status',
                'total_files',
                'duplicate_files',
                'uploadable_files',
                'expires_at',
                'files' => [
                    '*' => ['uuid', 'filename', 'status'],
                ],
            ],
        ]);

    $this->assertDatabaseCount('bulk_upload_sessions', 1);
    $this->assertDatabaseCount('bulk_upload_files', 2);
});

it('detects duplicate files during session creation', function () {
    // Create an existing file with a known hash
    $knownHash = hash('sha256', 'known-content');
    File::factory()->create([
        'user_id' => $this->user->id,
        'file_hash' => $knownHash,
        'status' => 'completed',
    ]);

    $response = $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'receipt',
        'files' => [
            [
                'filename' => 'duplicate.pdf',
                'size' => 100000,
                'hash' => $knownHash,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
            ],
            [
                'filename' => 'unique.pdf',
                'size' => 200000,
                'hash' => hash('sha256', 'unique-content'),
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.duplicate_files', 1)
        ->assertJsonPath('data.uploadable_files', 1);

    // Verify the duplicate file is marked correctly
    $duplicateFile = BulkUploadFile::where('file_hash', $knownHash)->first();
    expect($duplicateFile->status)->toBe(BulkUploadFileStatus::Duplicate);
});

it('creates a bulk upload session with office document formats', function () {
    $response = $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'document',
        'files' => [
            [
                'filename' => 'report.docx',
                'size' => 245000,
                'hash' => hash('sha256', 'docx-content'),
                'extension' => 'docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            [
                'filename' => 'spreadsheet.xlsx',
                'size' => 120000,
                'hash' => hash('sha256', 'xlsx-content'),
                'extension' => 'xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            [
                'filename' => 'notes.txt',
                'size' => 5000,
                'hash' => hash('sha256', 'txt-content'),
                'extension' => 'txt',
                'mime_type' => 'text/plain',
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.total_files', 3)
        ->assertJsonPath('data.uploadable_files', 3);
});

it('validates session creation request', function () {
    $this->postJson('/api/v1/bulk/sessions', [])
        ->assertStatus(422);

    $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'invalid',
        'files' => [],
    ])->assertStatus(422);

    $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'receipt',
        'files' => [
            [
                'filename' => 'test.pdf',
                'size' => 245000,
                'hash' => 'not-a-valid-hash',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
            ],
        ],
    ])->assertStatus(422);
});

it('strips sha256 prefix from hashes', function () {
    $rawHash = hash('sha256', 'test-content');

    $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'receipt',
        'files' => [
            [
                'filename' => 'test.pdf',
                'size' => 100000,
                'hash' => "sha256:{$rawHash}",
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
            ],
        ],
    ])->assertStatus(201);

    $file = BulkUploadFile::first();
    expect($file->file_hash)->toBe($rawHash);
});

it('supports per-file overrides', function () {
    $response = $this->postJson('/api/v1/bulk/sessions', [
        'file_type' => 'receipt',
        'note' => 'Batch note',
        'files' => [
            [
                'filename' => 'override.pdf',
                'size' => 100000,
                'hash' => hash('sha256', 'override-content'),
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_type' => 'document',
                'note' => 'Individual note',
            ],
        ],
    ]);

    $response->assertStatus(201);

    $file = BulkUploadFile::first();
    expect($file->file_type)->toBe('document');
    expect($file->note)->toBe('Individual note');
    expect($file->getEffectiveFileType())->toBe('document');
});

// --- Session Status ---

it('returns session status', function () {
    $session = BulkUploadSession::factory()->create([
        'user_id' => $this->user->id,
        'total_files' => 5,
    ]);

    BulkUploadFile::factory()->count(3)->create([
        'bulk_upload_session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => BulkUploadFileStatus::Pending,
    ]);

    BulkUploadFile::factory()->count(2)->create([
        'bulk_upload_session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => BulkUploadFileStatus::Duplicate,
    ]);

    $response = $this->getJson("/api/v1/bulk/sessions/{$session->uuid}");

    $response->assertSuccessful()
        ->assertJsonPath('data.session_id', $session->uuid)
        ->assertJsonPath('data.summary.total_files', 5)
        ->assertJsonPath('data.summary.duplicate_count', 2);
});

it('returns 404 for another users session', function () {
    $otherUser = User::factory()->create();
    $session = BulkUploadSession::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $this->getJson("/api/v1/bulk/sessions/{$session->uuid}")
        ->assertNotFound();
});

// --- Session Listing ---

it('lists users bulk upload sessions', function () {
    BulkUploadSession::factory()->count(3)->create([
        'user_id' => $this->user->id,
    ]);

    // Other user's session should not appear
    BulkUploadSession::factory()->create([
        'user_id' => User::factory()->create()->id,
    ]);

    $response = $this->getJson('/api/v1/bulk/sessions');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

// --- Session Cancellation ---

it('cancels an active session', function () {
    $session = BulkUploadSession::factory()->uploading()->create([
        'user_id' => $this->user->id,
        'total_files' => 2,
    ]);

    BulkUploadFile::factory()->count(2)->create([
        'bulk_upload_session_id' => $session->id,
        'user_id' => $this->user->id,
        'status' => BulkUploadFileStatus::Presigned,
    ]);

    $response = $this->postJson("/api/v1/bulk/sessions/{$session->uuid}/cancel");

    $response->assertSuccessful();

    $session->refresh();
    expect($session->status)->toBe(BulkUploadSessionStatus::Cancelled);
});

it('cannot cancel a completed session', function () {
    $session = BulkUploadSession::factory()->completed()->create([
        'user_id' => $this->user->id,
    ]);

    $this->postJson("/api/v1/bulk/sessions/{$session->uuid}/cancel")
        ->assertStatus(422);
});

// --- Model Logic ---

it('falls back to session defaults for file type', function () {
    $session = BulkUploadSession::factory()->create([
        'user_id' => $this->user->id,
        'default_file_type' => 'document',
        'default_note' => 'Session note',
    ]);

    $file = BulkUploadFile::factory()->create([
        'bulk_upload_session_id' => $session->id,
        'user_id' => $this->user->id,
        'file_type' => null,
        'note' => null,
    ]);

    expect($file->getEffectiveFileType())->toBe('document');
    expect($file->getEffectiveNote())->toBe('Session note');
});

it('prefers per-file overrides over session defaults', function () {
    $session = BulkUploadSession::factory()->create([
        'user_id' => $this->user->id,
        'default_file_type' => 'receipt',
        'default_note' => 'Session note',
    ]);

    $file = BulkUploadFile::factory()->create([
        'bulk_upload_session_id' => $session->id,
        'user_id' => $this->user->id,
        'file_type' => 'document',
        'note' => 'File note',
    ]);

    expect($file->getEffectiveFileType())->toBe('document');
    expect($file->getEffectiveNote())->toBe('File note');
});

it('correctly identifies expired sessions', function () {
    $expired = BulkUploadSession::factory()->expired()->create([
        'user_id' => $this->user->id,
    ]);

    $active = BulkUploadSession::factory()->create([
        'user_id' => $this->user->id,
    ]);

    expect($expired->isExpired())->toBeTrue();
    expect($expired->isActive())->toBeFalse();
    expect($active->isExpired())->toBeFalse();
    expect($active->isActive())->toBeTrue();
});

it('correctly identifies terminal file statuses', function () {
    expect(BulkUploadFileStatus::Completed->isTerminal())->toBeTrue();
    expect(BulkUploadFileStatus::Failed->isTerminal())->toBeTrue();
    expect(BulkUploadFileStatus::Duplicate->isTerminal())->toBeTrue();
    expect(BulkUploadFileStatus::Pending->isTerminal())->toBeFalse();
    expect(BulkUploadFileStatus::Processing->isTerminal())->toBeFalse();
});

// --- Auth ---

it('requires authentication for bulk endpoints', function () {
    // Reset auth
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/bulk/sessions', [])->assertUnauthorized();
    $this->getJson('/api/v1/bulk/sessions')->assertUnauthorized();
    $this->getJson('/api/v1/bulk/sessions/fake-uuid')->assertUnauthorized();
});
