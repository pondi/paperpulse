<?php

namespace App\Services;

use App\Jobs\GenerateArchiveExport;
use App\Models\ArchiveExport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ArchiveExportService
{
    public function queue(Request $request, string $format, array $filters, int $total): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $export = $user->getConnection()->transaction(function () use ($user, $format, $filters, $total): ArchiveExport {
            User::query()->lockForUpdate()->findOrFail($user->id);
            if (ArchiveExport::where('user_id', $user->id)->whereIn('status', ['pending', 'processing'])->where('expires_at', '>', now())->count() >= config('exports.active_per_user')) {
                throw ValidationException::withMessages(['export' => 'Wait for an active export to finish before starting another.']);
            }
            $export = ArchiveExport::create(['user_id' => $user->id, 'format' => $format, 'filters' => $filters,
                'total' => $total, 'expires_at' => now()->addHours(config('exports.expires_hours'))]);
            GenerateArchiveExport::dispatch($export->id)->afterCommit();

            return $export;
        });

        return $request->expectsJson()
            ? response()->json(['id' => $export->id, 'status' => $export->status, 'status_url' => route('exports.status', $export)], 202)
            : redirect()->route('exports.index');
    }
}
