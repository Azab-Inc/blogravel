<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $existingUser = User::where('email', $invitation->email)->first();

        return view('invitations.accept', [
            'invitation' => $invitation,
            'existingUser' => $existingUser,
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

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            $existingUser->update([
                'tenant_id' => $invitation->tenant_id,
                'role' => $invitation->role,
            ]);

            Auth::login($existingUser);
        } else {
            $request->validate([
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = User::create([
                'name' => trim($request->first_name.' '.$request->last_name),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'tenant_id' => $invitation->tenant_id,
                'role' => $invitation->role,
            ]);

            Auth::login($user);
        }

        $invitation->update(['accepted_at' => now()]);

        return redirect('/admin');
    }
}
