<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
        ]);

        // Check if user already exists or an invitation is already present.
        // Always respond with a generic success message to avoid email enumeration.
        $existingUser = User::where('email', $validated['email'])->exists();
        $existingRequest = Invitation::where('email', $validated['email'])->first();

        if ($existingUser || ($existingRequest && ($existingRequest->isSent() || $existingRequest->isPending()))) {
            return response()->json([
                'message' => 'Thank you for your interest! We\'ll review your request and get back to you soon.',
            ], 201);
        }

        // Create the invitation request with status 'pending'
        Invitation::create(array_merge($validated, ['status' => 'pending']));

        return response()->json([
            'message' => 'Thank you for your interest! We\'ll review your request and get back to you soon.',
        ], 201);
    }
}
