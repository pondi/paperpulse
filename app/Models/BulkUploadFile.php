<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkUploadFileStatus;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $bulk_upload_session_id
 * @property int $user_id
 * @property int|null $file_id
 * @property string $original_filename
 * @property string|null $original_path
 * @property int $file_size
 * @property string $file_hash
 * @property string $file_extension
 * @property string $mime_type
 * @property BulkUploadFileStatus $status
 * @property string|null $file_type
 * @property array<int>|null $collection_ids
 * @property array<int>|null $tag_ids
 * @property string|null $note
 * @property string|null $s3_key
 * @property Carbon|null $presigned_expires_at
 * @property string|null $job_id
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BulkUploadSession $session
 * @property-read User $user
 * @property-read File|null $file
 */
class BulkUploadFile extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'bulk_upload_session_id',
        'user_id',
        'file_id',
        'original_filename',
        'original_path',
        'file_size',
        'file_hash',
        'file_extension',
        'mime_type',
        'status',
        'file_type',
        'collection_ids',
        'tag_ids',
        'note',
        's3_key',
        'presigned_expires_at',
        'job_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkUploadFileStatus::class,
            'collection_ids' => 'array',
            'tag_ids' => 'array',
            'file_size' => 'integer',
            'presigned_expires_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BulkUploadSession::class, 'bulk_upload_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the effective file_type, falling back to session default.
     */
    public function getEffectiveFileType(): string
    {
        return $this->file_type ?? $this->session->default_file_type;
    }

    /**
     * Get effective collection IDs, falling back to session defaults.
     *
     * @return array<int>
     */
    public function getEffectiveCollectionIds(): array
    {
        return $this->collection_ids ?? $this->session->default_collection_ids ?? [];
    }

    /**
     * Get effective tag IDs, falling back to session defaults.
     *
     * @return array<int>
     */
    public function getEffectiveTagIds(): array
    {
        return $this->tag_ids ?? $this->session->default_tag_ids ?? [];
    }

    /**
     * Get effective note, falling back to session default.
     */
    public function getEffectiveNote(): ?string
    {
        return $this->note ?? $this->session->default_note;
    }

    public function isPresignExpired(): bool
    {
        return $this->presigned_expires_at !== null && $this->presigned_expires_at->isPast();
    }

    public function markAsDuplicate(?File $existingFile = null): void
    {
        $this->update([
            'status' => BulkUploadFileStatus::Duplicate,
            'error_message' => $existingFile
                ? "Duplicate of file ID {$existingFile->id} (guid: {$existingFile->guid})"
                : 'Duplicate file detected',
        ]);
    }
}
