<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\SharingService;
use Illuminate\Http\Request;

class DocumentShareController extends Controller
{
    protected $sharingService;

    public function __construct(SharingService $sharingService)
    {
        $this->sharingService = $sharingService;
    }

    /**
     * Share document with another user
     */
    public function share(Request $request, Document $document)
    {
        $this->authorize('share', $document);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);

        try {
            $share = $this->sharingService->shareDocument(
                $document,
                $validated['email'],
                $validated['permission']
            );

            return back()->with('success', 'Document shared successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove document share
     */
    public function unshare(Document $document, int $userId)
    {
        $this->authorize('share', $document);

        try {
            $this->sharingService->unshareDocument($document, $userId);

            return back()->with('success', 'Share removed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove share');
        }
    }

    /**
     * Get shares for a document (API)
     */
    public function getShares(Document $document)
    {
        $this->authorize('view', $document);

        $shares = \App\Models\FileShare::where('file_id', $document->file_id)
            ->where('file_type', 'document')
            ->with('sharedWithUser:id,name,email')
            ->get();

        return response()->json($shares);
    }
}
