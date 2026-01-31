<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inertia\FileInertiaResource;
use App\Models\File;
use App\Services\Files\FileReprocessingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FileManagementController extends Controller
{
    public function index(Request $request)
    {
        // Validate and get per_page value (50, 100, 200, or 999999 for "all")
        $perPage = $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 200, 999999]) ? (int) $perPage : 50;

        $filesQuery = File::query()
            ->whereIn('status', ['failed', 'processing', 'pending', 'completed'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->orderByDesc('uploaded_at');

        $files = $filesQuery
            ->paginate($perPage)
            ->through(fn (File $file) => FileInertiaResource::forIndex($file));

        // Get statistics for all files (not just current page)
        $stats = [
            'total' => File::whereIn('status', ['failed', 'processing', 'pending', 'completed'])->count(),
            'failed' => File::where('status', 'failed')->count(),
            'processing' => File::where('status', 'processing')->count(),
            'pending' => File::where('status', 'pending')->count(),
            'completed' => File::where('status', 'completed')->count(),
        ];

        return Inertia::render('Files/Index', [
            'files' => $files,
            'stats' => $stats,
            'filters' => [
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

    public function changeTypeAndReprocess(Request $request, File $file, FileReprocessingService $reprocessingService)
    {
        if ($file->status !== 'failed') {
            return back()->with('error', 'Only failed files can be changed and restarted.');
        }

        $validated = $request->validate([
            'file_type' => 'required|string|in:receipt,document',
        ]);

        $result = $reprocessingService->changeTypeAndReprocess($file, $validated['file_type']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
