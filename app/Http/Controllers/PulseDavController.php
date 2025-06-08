<?php

namespace App\Http\Controllers;

use App\Models\PulseDavFile;
use App\Models\Tag;
use App\Services\PulseDavService;
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
            
        // Get user's tags for the tag selector
        $tags = auth()->user()->tags()->orderBy('name')->get();

        \Log::info('PulseDav index', [
            'user_id' => auth()->id(),
            'files_count' => $files->count(),
            'tags_count' => $tags->count(),
        ]);

        return Inertia::render('PulseDav/Index', [
            'files' => $files,
            'tags' => $tags,
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
            'file_type' => 'nullable|in:receipt,document',
        ]);

        $queued = $this->pulseDavService->processFiles(
            $request->file_ids,
            $request->user(),
            $request->file_type ?? 'receipt'
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

    /**
     * Get folder structure
     */
    public function folders(Request $request)
    {
        $items = $this->pulseDavService->listUserFilesWithFolders($request->user());
        $hierarchy = $this->pulseDavService->buildFolderHierarchy($items);

        return response()->json([
            'hierarchy' => $hierarchy,
            'total_items' => count($items),
        ]);
    }

    /**
     * Get folder contents
     */
    public function folderContents(Request $request)
    {
        $request->validate([
            'folder_path' => 'nullable|string',
        ]);

        $contents = $this->pulseDavService->getFolderContents(
            $request->user(),
            $request->input('folder_path', '')
        );

        return response()->json([
            'contents' => $contents,
        ]);
    }

    /**
     * Import selected files/folders with tags
     */
    public function importSelections(Request $request)
    {
        $request->validate([
            'selections' => 'required|array',
            'selections.*.s3_path' => 'required|string',
            'file_type' => 'required|in:receipt,document',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'notes' => 'nullable|string|max:500',
        ]);

        // Verify tags belong to user
        if ($request->has('tag_ids')) {
            $userTagIds = auth()->user()->tags()->pluck('id')->toArray();
            $invalidTags = array_diff($request->tag_ids, $userTagIds);
            if (!empty($invalidTags)) {
                return response()->json([
                    'error' => 'Invalid tags selected',
                ], 422);
            }
        }

        $result = $this->pulseDavService->importSelections(
            $request->user(),
            $request->selections,
            [
                'file_type' => $request->file_type,
                'tag_ids' => $request->tag_ids ?? [],
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'message' => "Imported {$result['imported']} files successfully",
            'batch_id' => $result['batch_id'],
            'imported' => $result['imported'],
        ]);
    }

    /**
     * Update folder tags
     */
    public function updateFolderTags(Request $request)
    {
        $request->validate([
            'folder_path' => 'required|string',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        // Verify tags belong to user
        if ($request->has('tag_ids')) {
            $userTagIds = auth()->user()->tags()->pluck('id')->toArray();
            $invalidTags = array_diff($request->tag_ids, $userTagIds);
            if (!empty($invalidTags)) {
                return response()->json([
                    'error' => 'Invalid tags selected',
                ], 422);
            }
        }

        $folder = $this->pulseDavService->updateFolderTags(
            $request->user(),
            $request->folder_path,
            $request->tag_ids ?? []
        );

        return response()->json([
            'message' => 'Folder tags updated successfully',
            'folder' => $folder,
        ]);
    }

    /**
     * Sync files with folder support
     */
    public function syncWithFolders(Request $request)
    {
        $synced = $this->pulseDavService->syncS3FilesWithFolders($request->user());

        return response()->json([
            'message' => "Synced {$synced} new files/folders from scanner",
            'synced' => $synced,
        ]);
    }

    /**
     * Search tags for autocomplete
     */
    public function searchTags(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|max:50',
        ]);

        $query = $request->input('query', '');
        
        $tags = auth()->user()->tags()
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'color']);

        return response()->json([
            'tags' => $tags,
        ]);
    }

    /**
     * Create a new tag
     */
    public function createTag(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tag = auth()->user()->tags()->firstOrCreate(
            ['name' => strtolower(trim($request->name))],
            ['color' => $request->color ?? '#' . substr(md5($request->name), 0, 6)]
        );

        return response()->json([
            'tag' => $tag,
        ]);
    }
}
