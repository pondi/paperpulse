<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveDuplicateRequest;
use App\Http\Resources\Inertia\DuplicateFlagInertiaResource;
use App\Models\DuplicateFlag;
use App\Models\File;
use App\Services\Files\FileDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DuplicateController extends Controller
{
    public function index(Request $request)
    {
        $duplicates = DuplicateFlag::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->with([
                'file.primaryEntity.entity',
                'duplicateFile.primaryEntity.entity',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DuplicateFlag $flag) => DuplicateFlagInertiaResource::forIndex($flag)->toArray(request()));

        return Inertia::render('Duplicates/Index', [
            'duplicates' => $duplicates,
        ]);
    }

    public function resolve(ResolveDuplicateRequest $request, DuplicateFlag $duplicateFlag, FileDeletionService $deletionService): RedirectResponse
    {
        $deleteFileId = (int) $request->validated('delete_file_id');
        $duplicateFlag->getConnection()->transaction(function () use ($request, $duplicateFlag, $deleteFileId, $deletionService): void {
            $duplicateFlag = DuplicateFlag::query()->lockForUpdate()->findOrFail($duplicateFlag->id);
            if (! in_array($deleteFileId, [$duplicateFlag->file_id, $duplicateFlag->duplicate_file_id], true)) {
                throw ValidationException::withMessages(['delete_file_id' => 'Invalid file selection for resolution']);
            }
            if ($duplicateFlag->status === 'resolved') {
                if ($duplicateFlag->resolved_file_id !== $deleteFileId) {
                    throw ValidationException::withMessages(['delete_file_id' => 'This duplicate has already been resolved.']);
                }

                return;
            }

            $file = File::query()->where('user_id', $request->user()->id)->find($deleteFileId);
            if (! $file) {
                throw ValidationException::withMessages(['delete_file_id' => 'File not found']);
            }
            $deletionService->deleteFile($file, $request->user()->id);
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

        });

        return back();
    }

    public function ignore(Request $request, DuplicateFlag $duplicateFlag)
    {
        $this->authorize('delete', $duplicateFlag);

        $duplicateFlag->delete();

        return back();
    }
}
