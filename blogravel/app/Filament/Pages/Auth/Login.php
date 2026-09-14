<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        $subheading = parent::getSubheading();
        $subheadingHtml = $subheading instanceof Htmlable ? $subheading->toHtml() : (string) $subheading;
        $recoveryLink = '<a href="'.e(route('filament.admin.auth.recover-account')).'">Recover account</a>';

        return new HtmlString(trim($subheadingHtml.' Need to recover your account? '.$recoveryLink));
    }
}
