<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class WebDavAuthController extends Controller
{
    public function __construct()
    {
        // Rate limiting is handled in routes/api.php
    }

    /**
     * Authenticate PulseDav users
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request)
    {
        // Check if PulseDav authentication is enabled
        if (!config('services.pulsedav.auth_enabled', false)) {
            return response()->json([
                'error' => 'PulseDav authentication is disabled'
            ], 403);
        }

        $request->validate([
            'username' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // Return user information for PulseDav
        return response()->json([
            'user_id' => $user->id,
            'username' => $user->email,
        ]);
    }
}