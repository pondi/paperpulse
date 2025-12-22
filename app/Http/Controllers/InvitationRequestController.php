<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvitationRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
        ]);

        $existingUser = User::where('email', $validated['email'])->exists();
        $existingRequest = Invitation::where('email', $validated['email'])->first();

        if ($existingUser) {
            Log::info('[InvitationRequest] Skipped - user exists', ['email' => $validated['email']]);
        } elseif ($existingRequest && ($existingRequest->isSent() || $existingRequest->isPending())) {
            Log::info('[InvitationRequest] Skipped - duplicate request', [
                'email' => $validated['email'],
                'status' => $existingRequest->status,
            ]);
        } else {
            $invitation = Invitation::create(array_merge($validated, ['status' => 'pending']));
            Log::info('[InvitationRequest] Created', [
                'id' => $invitation->id,
                'email' => $validated['email'],
                'name' => $validated['name'],
            ]);
        }

        return back();
    }
}
