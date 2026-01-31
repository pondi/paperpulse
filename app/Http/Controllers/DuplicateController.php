<?php

namespace App\Http\Controllers;

use App\Http\Resources\Inertia\DuplicateFlagInertiaResource;
use App\Models\DuplicateFlag;
use App\Models\File;
use App\Services\DocumentService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DuplicateController extends Controller
{
    public function index(Request $request)
    {
        $duplicates = DuplicateFlag::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->with([
                'file.primaryReceipt.merchant',
                'file.primaryDocument',
                'duplicateFile.primaryReceipt.merchant',
                'duplicateFile.primaryDocument',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DuplicateFlag $flag) => DuplicateFlagInertiaResource::forIndex($flag)->toArray(request()));

        return Inertia::render('Duplicates/Index', [
            'duplicates' => $duplicates,
        ]);
    }

    public function resolve(Request $request, DuplicateFlag $duplicateFlag)
    {
        $this->authorize('update', $duplicateFlag);

        $validated = $request->validate([
            'delete_file_id' => 'required|integer',
        ]);

        $deleteFileId = (int) $validated['delete_file_id'];
        $allowedIds = [$duplicateFlag->file_id, $duplicateFlag->duplicate_file_id];

        if (! in_array($deleteFileId, $allowedIds, true)) {
            return back()->withErrors(['delete_file_id' => 'Invalid file selection for resolution']);
        }

        $file = File::where('id', $deleteFileId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $file) {
            return back()->withErrors(['delete_file_id' => 'File not found']);
        }

        DB::transaction(function () use ($duplicateFlag, $file) {
            $this->deleteFileAssets($file);

            $duplicateFlag->status = 'resolved';
            $duplicateFlag->resolved_file_id = $file->id;
            $duplicateFlag->resolved_at = now();
            $duplicateFlag->save();

            DuplicateFlag::where('user_id', $duplicateFlag->user_id)
                ->where(function ($query) use ($file) {
                    $query->where('file_id', $file->id)
                        ->orWhere('duplicate_file_id', $file->id);
                })
                ->where('id', '!=', $duplicateFlag->id)
                ->delete();

            $file->delete();
        });

        return back();
    }

    public function ignore(Request $request, DuplicateFlag $duplicateFlag)
    {
        $this->authorize('delete', $duplicateFlag);

        $duplicateFlag->delete();

        return back();
    }

    protected function deleteFileAssets(File $file): void
    {
        $storageService = app(StorageService::class);
        $documentService = app(DocumentService::class);

        if ($file->guid && $file->user_id) {
            $typeFolder = $file->file_type === 'document' ? 'documents' : 'receipts';
            $directoryPath = trim("{$typeFolder}/{$file->user_id}/{$file->guid}", '/');
            $storageService->deleteDirectory($directoryPath);
        }

        $paths = [
            $file->s3_original_path,
            $file->s3_processed_path,
            $file->s3_archive_path,
            $file->s3_image_path,
        ];

        foreach (array_filter($paths) as $path) {
            $storageService->deleteFile($path);
        }

        if ($file->guid) {
            $extension = $file->fileExtension ?? 'pdf';
            $typeFolder = $file->file_type === 'document' ? 'documents' : 'receipts';

            $documentService->deleteDocument($file->guid, 'DuplicateResolution', $typeFolder, $extension);

            if ($file->file_type === 'receipt') {
                $documentService->deleteDocument($file->guid, 'DuplicateResolution', 'receipts', 'jpg');
            }
        }

        DB::table('file_tags')->where('file_id', $file->id)->delete();
        DB::table('file_shares')->where('file_id', $file->id)->delete();
    }
}
