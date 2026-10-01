<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $invitation = null;
        $token = $request->input('token');

        if ($token) {
            $invitation = Invitation::findValidByToken($token);

            if (! $invitation) {
                return redirect()->route('login')->withErrors([
                    'invitation' => 'Invalid or expired invitation link.',
                ]);
            }
        }

        return Inertia::render('Auth/Register', [
            'invitation' => $invitation ? [
                'email' => $invitation->email,
                'token' => $token,
            ] : null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $connection = (new Invitation)->getConnection();

        $user = $connection->transaction(function () use ($data, $connection): User {
            $invitation = Invitation::query()
                ->where('token', $data['invitation_token'])
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isValid()) {
                throw ValidationException::withMessages([
                    'invitation_token' => 'Invalid or expired invitation.',
                ]);
            }

            if ($invitation->email !== $data['email']) {
                throw ValidationException::withMessages([
                    'email' => 'Email must match the invited email address.',
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $invitation->markAsUsed();
            $connection->afterCommit(fn () => event(new Registered($user)));

            return $user;
        });

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
