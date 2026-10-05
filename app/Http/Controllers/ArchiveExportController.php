<?php

namespace App\Http\Controllers;

use App\Models\ArchiveExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchiveExportController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $exports = ArchiveExport::where('user_id', $request->user()->id)->latest()->limit(20)->get()
            ->map(fn ($export) => $this->details($export));

        return $request->expectsJson() ? response()->json(['exports' => $exports]) : Inertia::render('Exports/Index', ['exports' => $exports]);
    }

    public function status(Request $request, int $export): JsonResponse
    {
        return response()->json($this->details(ArchiveExport::where('user_id', $request->user()->id)->findOrFail($export)));
    }

    public function download(Request $request, int $export): BinaryFileResponse
    {
        $export = ArchiveExport::where('user_id', $request->user()->id)->findOrFail($export);
        abort_unless($export->status === 'completed' && $export->expires_at->isFuture(), 410);

        return response()->download(Storage::disk('local')->path($export->path), 'archive.'.$export->format, ['Cache-Control' => 'private, no-store']);
    }

    private function details(ArchiveExport $export): array
    {
        return ['id' => $export->id, 'format' => $export->format, 'status' => $export->expires_at->isPast() ? 'expired' : $export->status, 'total' => $export->total,
            'processed' => $export->processed, 'error' => $export->error, 'expires_at' => $export->expires_at->toIso8601String(),
            'download_url' => $export->status === 'completed' && $export->expires_at->isFuture()
                ? URL::temporarySignedRoute('exports.download', $export->expires_at, ['export' => $export->id]) : null];
    }
}
