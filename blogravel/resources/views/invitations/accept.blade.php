<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Invitation - {{ $invitation->tenant->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">You are Invited!</h1>
            <p class="text-gray-600 mt-2">
                Join <strong>{{ $invitation->tenant->name }}</strong> as a <strong>{{ $invitation->role->label() }}</strong>
            </p>
        </div>

        @if ($existingUser)
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                <p class="text-sm text-blue-800">
                    We found an existing account for <strong>{{ $invitation->type === 'shareable' ? request('email') : $invitation->email }}</strong>.
                    Click below to join this team with your existing account.
                </p>
            </div>

            <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                @csrf
                @if ($invitation->type === 'shareable')
                    <input type="hidden" name="email" value="{{ request('email') }}">
                @endif
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    Join with Existing Account
                </button>
            </form>
        @else
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                <p class="text-sm text-yellow-800">
                    @if ($invitation->type === 'shareable')
                        Enter your details below to create your account and join this team.
                    @else
                        No account found for <strong>{{ $invitation->email }}</strong>.
                        Please create your account below.
                    @endif
                </p>
            </div>

            <form method="POST" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                @csrf
                <div class="space-y-4">
                    @if ($invitation->type === 'shareable')
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm border p-2">
                        </div>
                    @endif
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="first_name" id="first_name" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm border p-2">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm border p-2">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" id="password" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm border p-2">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm border p-2">
                    </div>
                </div>

                @error('first_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full mt-6 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    Create Account & Join
                </button>
            </form>
        @endif

        <p class="text-xs text-gray-400 text-center mt-6">
            This invitation expires on {{ $invitation->expires_at->format('F j, Y') }}.
        </p>
    </div>
</body>
</html>
