<?php

namespace App\Http\Controllers;

use App\Services\PulseDavService;
use App\Models\PulseDavFile;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PulseDavController extends Controller
{
    protected $pulseDavService;

    public function __construct(PulseDavService $pulseDavService)
    {
        $this->pulseDavService = $pulseDavService;
    }

    /**
     * Display the PulseDav file browser
     */
    public function index()
    {
        $files = PulseDavFile::where('user_id', auth()->id())
            ->orderBy('uploaded_at', 'desc')
            ->paginate(20);

        return Inertia::render('PulseDav/Index', [
            'files' => $files,
        ]);
    }

    /**
     * Sync files from S3
     */
    public function sync(Request $request)
    {
        $synced = $this->pulseDavService->syncS3Files($request->user());

        return response()->json([
            'message' => "Synced {$synced} new files from scanner",
            'synced' => $synced,
        ]);
    }

    /**
     * Process selected files
     */
    public function process(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:pulsedav_files,id',
        ]);

        $queued = $this->pulseDavService->processFiles(
            $request->file_ids,
            $request->user()
        );

        return response()->json([
            'message' => "Queued {$queued} files for processing",
            'queued' => $queued,
        ]);
    }

    /**
     * Get file status
     */
    public function status($id)
    {
        $file = PulseDavFile::where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => $this->pulseDavService->getProcessingStatus($file),
        ]);
    }

    /**
     * Delete a file
     */
    public function destroy($id)
    {
        $file = PulseDavFile::where('user_id', auth()->id())
            ->findOrFail($id);

        try {
            $this->pulseDavService->deleteFile($file);
            return response()->json([
                'message' => 'File deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete file',
            ], 500);
        }
    }
}