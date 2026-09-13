<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()
            ? to_route('filament.admin.pages.dashboard')
            : to_route('filament.admin.auth.login');
    }
}
