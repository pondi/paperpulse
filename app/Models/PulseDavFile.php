<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PulseDavFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pulsedav_files';

    protected $fillable = [
        'user_id',
        's3_path',
        'filename',
        'size',
        'status',
        'file_type',
        'uploaded_at',
        'processed_at',
        'error_message',
        'receipt_id',
        'document_id',
        'folder_path',
        'parent_folder',
        'depth',
        'is_folder',
        'folder_tag_ids',
        'import_batch_id',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_folder' => 'boolean',
        'folder_tag_ids' => 'array',
        'depth' => 'integer',
    ];

    /**
     * Get the user that owns the S3 file.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the receipt associated with this S3 file.
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * Get the document associated with this S3 file.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Scope a query to only include files for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include files with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending files.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if the file is processable.
     */
    public function isProcessable()
    {
        return in_array($this->status, ['pending', 'failed']);
    }

    /**
     * Mark the file as processing.
     */
    public function markAsProcessing()
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark the file as completed.
     */
    public function markAsCompleted($relatedId = null, $type = 'receipt')
    {
        $updateData = [
            'status' => 'completed',
            'processed_at' => now(),
            'error_message' => null,
        ];

        if ($type === 'receipt') {
            $updateData['receipt_id'] = $relatedId;
        } else {
            $updateData['document_id'] = $relatedId;
        }

        $this->update($updateData);
    }

    /**
     * Mark the file as failed.
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get the import batch relationship
     */
    public function importBatch()
    {
        return $this->belongsTo(PulseDavImportBatch::class, 'import_batch_id');
    }

    /**
     * Get tags from folder
     */
    public function getFolderTagsAttribute()
    {
        if (! $this->folder_tag_ids) {
            return collect();
        }

        return Tag::whereIn('id', $this->folder_tag_ids)->get();
    }

    /**
     * Get all inherited tags (from parent folders)
     */
    public function getInheritedTagsAttribute()
    {
        $tags = collect();

        // Add direct folder tags
        if ($this->folder_tag_ids) {
            $tags = $tags->concat(Tag::whereIn('id', $this->folder_tag_ids)->get());
        }

        // Get parent folder tags
        if ($this->parent_folder && $this->folder_path) {
            $parentPath = dirname($this->folder_path);
            if ($parentPath !== '.') {
                $parent = self::where('user_id', $this->user_id)
                    ->where('folder_path', $parentPath)
                    ->where('is_folder', true)
                    ->first();

                if ($parent) {
                    $tags = $tags->concat($parent->inherited_tags);
                }
            }
        }

        return $tags->unique('id');
    }

    /**
     * Scope to files/folders within a specific folder
     */
    public function scopeInFolder($query, $folderPath)
    {
        if (empty($folderPath) || $folderPath === '/') {
            // Root folder - items with no parent folder
            return $query->whereNull('parent_folder')->orWhere('parent_folder', '');
        }

        return $query->where('parent_folder', $folderPath);
    }

    /**
     * Scope to only folders
     */
    public function scopeFoldersOnly($query)
    {
        return $query->where('is_folder', true);
    }

    /**
     * Scope to only files (not folders)
     */
    public function scopeFilesOnly($query)
    {
        return $query->where('is_folder', false);
    }

    /**
     * Get child files and folders
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_folder', 'folder_path')
            ->where('user_id', $this->user_id);
    }

    /**
     * Extract folder path from S3 path
     */
    public static function extractFolderInfo($s3Path, $userPrefix)
    {
        // Remove user prefix (e.g., "incoming/123/")
        $relativePath = str_replace($userPrefix, '', $s3Path);

        // Remove trailing slash for folders
        $relativePath = rtrim($relativePath, '/');

        // Get directory path
        $folderPath = dirname($relativePath);
        if ($folderPath === '.') {
            $folderPath = null;
            $parentFolder = null;
            $depth = 0;
        } else {
            $parentFolder = basename(dirname($folderPath)) !== '.' ? basename(dirname($folderPath)) : null;
            $depth = substr_count($folderPath, '/') + 1;
        }

        // For folder entries, the folder path is the relative path itself
        if (substr($s3Path, -1) === '/') {
            $folderPath = $relativePath;
            $parentFolder = dirname($relativePath) !== '.' ? dirname($relativePath) : null;
            $depth = substr_count($relativePath, '/');
        }

        return [
            'folder_path' => $folderPath,
            'parent_folder' => $parentFolder,
            'depth' => $depth,
        ];
    }
}
