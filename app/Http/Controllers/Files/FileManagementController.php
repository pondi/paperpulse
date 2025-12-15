<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\Files\FileReprocessingService;
use App\Services\Files\FileTransformer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FileManagementController extends Controller
{
    public function index(Request $request)
    {
        $files = File::query()
            ->whereIn('status', ['failed', 'completed'])
            ->orderByRaw("case when status = 'failed' then 0 else 1 end")
            ->orderByDesc('uploaded_at')
            ->paginate(50)
            ->through(fn (File $file) => FileTransformer::forIndex($file));

        return Inertia::render('Files/Index', [
            'files' => $files,
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
