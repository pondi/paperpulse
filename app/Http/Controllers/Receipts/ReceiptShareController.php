<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Http\Request;

class ReceiptShareController extends Controller
{
    public function share(Request $request, Receipt $receipt)
    {
        $this->authorize('share', $receipt);

        $validated = $request->validate([
            'email' => 'required|email',
            'permission' => 'required|in:view,edit',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return back()->withErrors(['email' => 'User not found']);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['email' => 'You cannot share with yourself']);
        }

        try {
            $receipt->shareWith($user, $validated['permission']);

            return back()->with('success', 'Receipt shared successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function unshare(Receipt $receipt, User $user)
    {
        $this->authorize('share', $receipt);

        try {
            $receipt->unshareWith($user);

            return back()->with('success', 'Share removed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove share');
        }
    }
}