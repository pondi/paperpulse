<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\DuplicateFlag;
use App\Models\File;
use App\Services\DocumentService;
use App\Services\Duplicates\DuplicateFlagTransformer;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuplicateController extends BaseApiController
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:open,resolved',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DuplicateFlag::where('user_id', $request->user()->id)
            ->with([
                'file.primaryReceipt.merchant',
                'file.invoices',
                'duplicateFile.primaryReceipt.merchant',
                'duplicateFile.invoices',
            ])
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $duplicates = $query
            ->paginate($validated['per_page'] ?? 20)
            ->through(fn (DuplicateFlag $flag) => DuplicateFlagTransformer::forIndex($flag));

        return $this->paginated($duplicates, 'Duplicate flags retrieved successfully');
    }

    public function resolve(Request $request, DuplicateFlag $duplicateFlag)
    {
        if ($duplicateFlag->user_id !== $request->user()->id) {
            return $this->forbidden('Unauthorized duplicate access');
        }

        $validated = $request->validate([
            'delete_file_id' => 'required|integer',
        ]);

        $deleteFileId = (int) $validated['delete_file_id'];
        $allowedIds = [$duplicateFlag->file_id, $duplicateFlag->duplicate_file_id];

        if (! in_array($deleteFileId, $allowedIds, true)) {
            return $this->error('Invalid file selection for resolution', 422);
        }

        $file = File::where('id', $deleteFileId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $file) {
            return $this->notFound('File not found');
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

        return $this->success(
            DuplicateFlagTransformer::forIndex($duplicateFlag->fresh()),
            'Duplicate resolved successfully'
        );
    }

    public function ignore(Request $request, DuplicateFlag $duplicateFlag)
    {
        if ($duplicateFlag->user_id !== $request->user()->id) {
            return $this->forbidden('Unauthorized duplicate access');
        }

        $duplicateFlag->delete();

        return $this->success(null, 'Duplicate ignored successfully');
    }

    public function destroy(Request $request, DuplicateFlag $duplicateFlag)
    {
        return $this->ignore($request, $duplicateFlag);
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
