<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkUploadFileStatus: string
{
    case Pending = 'pending';
    case Presigned = 'presigned';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Confirming = 'confirming';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Duplicate = 'duplicate';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Duplicate,
            self::Skipped,
        ]);
    }

    public function canPresign(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Presigned,
            self::Failed,
        ]);
    }

    public function canConfirm(): bool
    {
        return in_array($this, [
            self::Presigned,
            self::Uploading,
            self::Uploaded,
            self::Failed,
        ]);
    }
}
