<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkUploadSessionStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Completing = 'completing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
