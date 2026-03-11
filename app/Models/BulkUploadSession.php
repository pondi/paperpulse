<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BulkUploadFileStatus;
use App\Enums\BulkUploadSessionStatus;
use App\Traits\BelongsToUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property BulkUploadSessionStatus $status
 * @property int $total_files
 * @property int $uploaded_count
 * @property int $completed_count
 * @property int $failed_count
 * @property int $duplicate_count
 * @property string $default_file_type
 * @property array<int>|null $default_collection_ids
 * @property array<int>|null $default_tag_ids
 * @property string|null $default_note
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, BulkUploadFile> $files
 */
class BulkUploadSession extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'total_files',
        'uploaded_count',
        'completed_count',
        'failed_count',
        'duplicate_count',
        'default_file_type',
        'default_collection_ids',
        'default_tag_ids',
        'default_note',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkUploadSessionStatus::class,
            'default_collection_ids' => 'array',
            'default_tag_ids' => 'array',
            'total_files' => 'integer',
            'uploaded_count' => 'integer',
            'completed_count' => 'integer',
            'failed_count' => 'integer',
            'duplicate_count' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BulkUploadFile::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isExpired() && in_array($this->status, [
            BulkUploadSessionStatus::Pending,
            BulkUploadSessionStatus::Uploading,
            BulkUploadSessionStatus::Completing,
        ]);
    }

    public function extendExpiry(int $hours = 24): void
    {
        $this->update(['expires_at' => now()->addHours($hours)]);
    }

    public function refreshCounts(): void
    {
        $this->update([
            'uploaded_count' => $this->files()->whereIn('status', [
                BulkUploadFileStatus::Uploaded,
                BulkUploadFileStatus::Confirming,
                BulkUploadFileStatus::Processing,
                BulkUploadFileStatus::Completed,
            ])->count(),
            'completed_count' => $this->files()->where('status', BulkUploadFileStatus::Completed)->count(),
            'failed_count' => $this->files()->where('status', BulkUploadFileStatus::Failed)->count(),
            'duplicate_count' => $this->files()->where('status', BulkUploadFileStatus::Duplicate)->count(),
        ]);
    }

    public function checkCompletion(): void
    {
        $this->refreshCounts();

        $terminalCount = $this->completed_count + $this->failed_count + $this->duplicate_count;

        if ($terminalCount >= $this->total_files) {
            $this->update([
                'status' => $this->failed_count > 0 && $this->completed_count === 0
                    ? BulkUploadSessionStatus::Failed
                    : BulkUploadSessionStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }
}
