<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentDownloadRequest;
use App\Models\File as SourceFile;
use App\Services\ArchiveExportService;
use App\Services\DocumentArchiveService;
use App\Services\Files\FileDeletionService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DocumentBulkController extends Controller
{
    public function destroyBulk(DocumentDownloadRequest $request, FileDeletionService $deletion): RedirectResponse
    {
        $deleted = 0;
        foreach (SourceFile::query()->where('user_id', $request->user()->id)->whereIn('id', $request->validated()['ids'])->get() as $file) {
            try {
                $deletion->deleteFile($file, $request->user()->id);
                $deleted++;
            } catch (Exception $e) {
                Log::error('Failed to delete document in bulk operation', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "{$deleted} documents deleted successfully");
    }

    public function downloadBulk(DocumentDownloadRequest $request, DocumentArchiveService $documents, ArchiveExportService $exports): Response
    {
        $ids = $request->validated()['ids'];
        $userId = $request->user()->id;
        $total = SourceFile::withoutGlobalScope('user')->where('user_id', $userId)->whereIn('id', $ids)->count();
        if ($total > config('exports.immediate_limit')) {
            return $exports->queue($request, 'zip', ['ids' => $ids], $total);
        }

        return response()->streamDownload(function () use ($documents, $userId, $ids): void {
            $directory = storage_path('app/private/export-download-'.Str::uuid());
            try {
                readfile($documents->write($userId, $ids, $directory));
            } finally {
                File::deleteDirectory($directory);
            }
        }, 'documents_'.now()->format('Y-m-d_H-i-s').'.zip', ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store']);
    }
}
