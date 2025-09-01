<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Illuminate\Http\Request;

class ReceiptShareController extends Controller
{
    /**
     * Get shares for a receipt (API)
     */
    public function getShares(Receipt $receipt)
    {
        $this->authorize('view', $receipt);

        $shares = \App\Models\FileShare::where('file_id', $receipt->file_id)
            ->where('file_type', 'receipt')
            ->with('sharedWithUser:id,name,email')
            ->get();

        return response()->json($shares);
    }

    /**
     * Share receipt with another user
     */
    public function share(Request $request, Receipt $receipt)
    {
        $this->authorize('share', $receipt);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'permission' => 'required|in:view,edit',
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if ($user->id === auth()->id()) {
            return back()->withErrors(['email' => 'You cannot share with yourself']);
        }

        try {
            app(\App\Services\SharingService::class)->shareFile(
                $receipt->file,
                [$user->id],
                $validated['permission']
            );

            return back()->with('success', 'Receipt shared successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove receipt share
     */
    public function unshare(Receipt $receipt, int $userId)
    {
        $this->authorize('share', $receipt);

        try {
            app(\App\Services\SharingService::class)->unshareFile($receipt->file, $userId);

            return back()->with('success', 'Share removed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove share');
        }
    }
}
