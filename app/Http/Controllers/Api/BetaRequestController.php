<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BetaRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BetaRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('beta_requests', 'email'),
                Rule::unique('users', 'email'),
            ],
            'company' => 'nullable|string|max:255',
        ]);

        // Check if user already exists
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'This email is already registered. Please sign in instead.',
            ], 422);
        }

        // Check if beta request already exists
        $existingRequest = BetaRequest::where('email', $validated['email'])->first();
        
        if ($existingRequest) {
            if ($existingRequest->isInvited()) {
                return response()->json([
                    'message' => 'An invitation has already been sent to this email address.',
                ], 422);
            }
            
            if ($existingRequest->isPending()) {
                return response()->json([
                    'message' => 'A beta request for this email is already pending.',
                ], 422);
            }
        }

        // Create the beta request
        BetaRequest::create($validated);

        return response()->json([
            'message' => 'Thank you for your interest! We\'ll review your request and get back to you soon.',
        ], 201);
    }
}