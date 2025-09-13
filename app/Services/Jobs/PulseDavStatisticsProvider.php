<?php

namespace App\Services\Jobs;

use App\Models\PulseDavFile;

class PulseDavStatisticsProvider
{
    public static function forUser(int $userId): array
    {
        $query = PulseDavFile::where('user_id', $userId);
        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
        ];
    }
}

