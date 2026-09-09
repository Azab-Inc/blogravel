<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            return redirect()->route('home')->withErrors([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'existingUser' => $this->resolveExistingUser($invitation, request()->input('email')),
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isValid()) {
            return redirect()->route('home')->withErrors([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        $existingUser = $this->resolveExistingUser($invitation, $request->input('email'));

        if ($existingUser) {
            $existingUser->update([
                'tenant_id' => $invitation->tenant_id,
                'role' => $invitation->role,
            ]);

            Auth::login($existingUser);
        } else {
            if ($invitation->type === 'shareable') {
                $validated = $request->validate([
                    'first_name' => ['required', 'string', 'max:255'],
                    'last_name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                    'password' => ['required', 'string', 'min:8', 'confirmed'],
                ]);

                $email = $validated['email'];
            } else {
                $validated = $request->validate([
                    'first_name' => ['required', 'string', 'max:255'],
                    'last_name' => ['required', 'string', 'max:255'],
                    'password' => ['required', 'string', 'min:8', 'confirmed'],
                ]);

                $email = $invitation->email;
            }

            $user = User::create([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $email,
                'password' => Hash::make($validated['password']),
                'tenant_id' => $invitation->tenant_id,
                'role' => $invitation->role,
            ]);

            Auth::login($user);
        }

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('filament.admin.pages.dashboard');
    }

    /**
     * For email invitations the invited email is fixed; for shareable links
     * the email is supplied by the acceptor.
     */
    private function resolveExistingUser(Invitation $invitation, ?string $email): ?User
    {
        return User::where('email', $invitation->type === 'shareable' ? $email : $invitation->email)->first();
    }
}
