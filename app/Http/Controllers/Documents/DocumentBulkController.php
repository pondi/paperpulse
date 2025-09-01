<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DocumentBulkController extends Controller
{
    /**
     * Bulk delete documents
     */
    public function destroyBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:documents,id',
        ]);

        $deleted = 0;
        foreach ($validated['ids'] as $id) {
            $document = Document::find($id);
            if ($document && auth()->user()->can('delete', $document)) {
                try {
                    // Delete from S3
                    if ($document->file && $document->file->s3_path) {
                        Storage::disk('paperpulse')->delete($document->file->s3_path);
                    }
                    $document->delete();
                    $deleted++;
                } catch (\Exception $e) {
                    Log::error('Failed to delete document in bulk operation', [
                        'document_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return back()->with('success', "{$deleted} documents deleted successfully");
    }

    /**
     * Bulk download documents
     */
    public function downloadBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:documents,id',
        ]);

        // Get user's documents with files
        $documents = Document::belongingToUser()
            ->whereIn('id', $validated['ids'])
            ->with(['file'])
            ->get();

        if ($documents->isEmpty()) {
            return back()->with('error', 'No accessible documents found');
        }

        $zipFileName = 'documents_'.now()->format('Y-m-d_H-i-s').'.zip';

        return new StreamedResponse(function () use ($documents) {
            // Create temporary file for the zip
            $zipPath = tempnam(sys_get_temp_dir(), 'bulk_download');
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                Log::error('Failed to create zip file for bulk download');

                return;
            }

            $filenameCounter = [];

            foreach ($documents as $document) {
                try {
                    if (! $document->file || ! $document->file->s3_path) {
                        Log::warning("Document {$document->id} has no file or s3_path");

                        continue;
                    }

                    // Get file content from S3
                    $fileContent = Storage::disk('paperpulse')->get($document->file->s3_path);

                    if ($fileContent === null) {
                        Log::warning("Could not retrieve file content for document {$document->id}");

                        continue;
                    }

                    // Generate safe filename using original filename
                    $originalName = $document->file->original_filename ?? $document->title;
                    $extension = $document->file->fileExtension ?? 'txt';

                    // Remove invalid characters
                    $safeFilename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $originalName);

                    // Handle duplicate filenames
                    $baseFilename = $safeFilename.'.'.$extension;
                    $finalFilename = $baseFilename;
                    $counter = 1;

                    while (isset($filenameCounter[$finalFilename])) {
                        $finalFilename = $safeFilename.'_'.$counter.'.'.$extension;
                        $counter++;
                    }

                    $filenameCounter[$finalFilename] = true;

                    // Add file to zip
                    $zip->addFromString($finalFilename, $fileContent);

                } catch (\Exception $e) {
                    Log::error("Error adding document {$document->id} to zip: ".$e->getMessage());

                    continue;
                }
            }

            $zip->close();

            // Stream the zip file
            if (file_exists($zipPath)) {
                readfile($zipPath);
                unlink($zipPath); // Clean up temporary file
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zipFileName.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
