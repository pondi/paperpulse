<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\CorrectFileTypeRequest;
use App\Http\Requests\Files\FileActivityRequest;
use App\Http\Resources\Inertia\FileInertiaResource;
use App\Models\File;
use App\Services\AI\Extractors\EntityExtractorFactory;
use App\Services\Files\FileReprocessingService;
use App\Services\Files\FileReviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FileManagementController extends Controller
{
    public function index(FileActivityRequest $request): Response
    {
        $perPage = $request->integer('per_page', 50);
        $sort = $request->input('sort', 'newest');
        $term = $request->string('query')->trim()->toString();
        $filesQuery = File::query()
            ->whereIn('status', ['failed', 'processing', 'pending', 'completed', 'needs_review'])
            ->when($request->filled('file_id'), fn ($query) => $query->whereKey($request->integer('file_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status));
        if ($term !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $filesQuery->whereLike('fileName', $pattern);
        }
        match ($sort) {
            'oldest' => $filesQuery->orderBy('uploaded_at'),
            'name' => $filesQuery->orderBy('fileName'),
            'status' => $filesQuery->orderBy('status')->orderByDesc('uploaded_at'),
            default => $filesQuery->orderByDesc('uploaded_at'),
        };
        $filesQuery->orderByDesc('id');

        $files = $filesQuery
            ->paginate($perPage)
            ->through(fn (File $file) => FileInertiaResource::forIndex($file)->toArray(request()));

        // Get statistics for all files (not just current page)
        $stats = [
            'total' => File::whereIn('status', ['failed', 'processing', 'pending', 'completed', 'needs_review'])->count(),
            'failed' => File::where('status', 'failed')->count(),
            'processing' => File::where('status', 'processing')->count(),
            'pending' => File::where('status', 'pending')->count(),
            'needs_review' => File::where('status', 'needs_review')->count(),
            'completed' => File::where('status', 'completed')->count(),
        ];

        return Inertia::render('Files/Index', [
            'files' => $files,
            'stats' => $stats,
            'reviewTypes' => EntityExtractorFactory::getSupportedTypes(),
            'filters' => [
                'file_id' => $request->filled('file_id') ? $request->integer('file_id') : null,
                'query' => $term,
                'sort' => $sort,
                'status' => $request->input('status', ''),
                'per_page' => $perPage,
            ],
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    public function reprocess(Request $request, File $file, FileReprocessingService $reprocessingService)
    {
        if ($file->status !== 'failed') {
            return back()->with('error', 'Only failed files can be restarted.');
        }

        $result = $reprocessingService->reprocessFile($file);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function changeTypeAndReprocess(CorrectFileTypeRequest $request, File $file, FileReprocessingService $reprocessingService, FileReviewService $reviewService)
    {
        $validated = $request->validated();
        if ($file->status === 'needs_review') {
            $reviewService->resolve($file, $validated['file_type']);

            return back()->with('success', 'The corrected document type has been queued for extraction.');
        }
        if ($file->status !== 'failed') {
            return back()->with('error', 'Only failed files can be changed and restarted.');
        }

        $result = $reprocessingService->changeTypeAndReprocess($file, $validated['file_type']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
