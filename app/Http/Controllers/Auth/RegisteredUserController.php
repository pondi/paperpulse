<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $invitation = null;
        $token = $request->get('token');
        
        if ($token) {
            $invitation = Invitation::findValidByToken($token);
            
            if (!$invitation) {
                return redirect()->route('login')->withErrors([
                    'invitation' => 'Invalid or expired invitation link.'
                ]);
            }
        }
        
        return Inertia::render('Auth/Register', [
            'invitation' => $invitation ? [
                'email' => $invitation->email,
                'token' => $token
            ] : null
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
        
        // If there's a token, validate it and ensure email matches
        if ($request->has('invitation_token')) {
            $validationRules['invitation_token'] = 'required|string';
            
            $request->validate($validationRules);
            
            $invitation = Invitation::findValidByToken($request->invitation_token);
            
            if (!$invitation) {
                return back()->withErrors([
                    'invitation_token' => 'Invalid or expired invitation.'
                ]);
            }
            
            if ($invitation->email !== $request->email) {
                return back()->withErrors([
                    'email' => 'Email must match the invited email address.'
                ]);
            }
            
            $invitation->markAsUsed();
        } else {
            // No invitation token provided - check if invitations are required
            // For now, we'll require invitations for all registrations
            return back()->withErrors([
                'invitation_token' => 'Registration requires an invitation.'
            ]);
        }
        
        $request->validate($validationRules);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
