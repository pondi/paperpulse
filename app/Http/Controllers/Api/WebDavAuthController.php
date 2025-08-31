<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebDavAuthController extends Controller
{
    /**
     * Authenticate PulseDav users
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request)
    {
        // Check if PulseDav authentication is enabled
        if (! config('services.pulsedav.auth_enabled', false)) {
            return response()->json([
                'error' => 'PulseDav authentication is disabled',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'username' => 'required|email|max:255',
                'password' => 'required|string|min:8|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Invalid request format',
            ], 422);
        }

        // Log authentication attempt (without password)
        Log::info('PulseDav authentication attempt', [
            'username' => $validated['username'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user = User::where('email', $validated['username'])->first();

        // Use timing-safe comparison to prevent timing attacks
        $validCredentials = $user && Hash::check($validated['password'], $user->password);

        // Always perform the same operations regardless of user existence
        if (! $user) {
            // Perform a dummy hash check to maintain consistent timing
            Hash::check('dummy_password', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
        }

        if (! $validCredentials) {
            Log::warning('Failed PulseDav authentication', [
                'username' => $validated['username'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid credentials',
            ], 401);
        }

        // Check if user email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'Email not verified',
            ], 403);
        }

        // Log successful authentication
        Log::info('Successful PulseDav authentication', [
            'user_id' => $user->id,
            'username' => $user->email,
            'ip' => $request->ip(),
        ]);

        // Return minimal user information for PulseDav
        return response()->json([
            'user_id' => $user->id,
            'username' => $user->email,
        ], 200, [
            'X-RateLimit-Remaining' => $request->header('X-RateLimit-Remaining'),
            'X-RateLimit-Limit' => $request->header('X-RateLimit-Limit'),
        ]);
    }
}
