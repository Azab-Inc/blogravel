<?php

namespace App\Filament\Pages\Auth;

use App\Enums\AccountRecoveryResult;
use App\Models\User;
use App\Services\AccountRecoveryService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class RecoverAccount extends SimplePage
{
    use WithRateLimiting;

    protected static ?string $title = 'Recover Account';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public ?string $message = null;

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->autocomplete('email')
                    ->autofocus(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('current-password'),
                Placeholder::make('message')
                    ->content(fn (): ?string => $this->message)
                    ->visible(fn (): bool => filled($this->message)),
            ]);
    }

    public function recover(AccountRecoveryService $recovery): void
    {
        try {
            $this->rateLimit(3);
        } catch (TooManyRequestsException $exception) {
            $this->message = 'Too many recovery attempts. Try again later.';
            Notification::make()->title($this->message)->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $result = $recovery->recover($data['email'], $data['password']);
        $this->message = $this->messageFor($result);

        if (in_array($result, [
            AccountRecoveryResult::RestoredUser,
            AccountRecoveryResult::RestoredUserAndTenant,
            AccountRecoveryResult::RestoredUserNeedsTenant,
        ], true)) {
            Notification::make()->title($this->message)->success()->send();

            if ($result === AccountRecoveryResult::RestoredUserNeedsTenant) {
                $user = User::withoutGlobalScopes()->findOrFail(session('recovery.needs_tenant_setup'));
                Auth::login($user);
                $this->redirect(route('filament.admin.tenant-setup'));

                return;
            }

            $this->redirect(route('filament.admin.auth.login'));

            return;
        }

        Notification::make()->title($this->message)->danger()->send();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('Enter your email and password to recover your account.');
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('recover')
                ->label('Recover account')
                ->submit('recover'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('recover')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->fullWidth()
                            ->key('form-actions'),
                    ]),
            ]);
    }

    private function messageFor(AccountRecoveryResult $result): string
    {
        return match ($result) {
            AccountRecoveryResult::RestoredUser => 'Your account has been recovered. You can now sign in.',
            AccountRecoveryResult::RestoredUserAndTenant => 'Your account and tenant have been recovered. You can now sign in.',
            AccountRecoveryResult::RestoredUserNeedsTenant => 'Your account has been recovered. Set up a new tenant before continuing.',
            AccountRecoveryResult::AdminRemovalBlocked => 'This account cannot be recovered because it was removed by an administrator.',
            AccountRecoveryResult::TenantClosureBlocked => 'This account cannot be recovered because its tenant was closed.',
            AccountRecoveryResult::Expired => 'This account recovery window has expired.',
            AccountRecoveryResult::ActiveEmailConflict => 'An active account already uses this email address.',
            AccountRecoveryResult::InvalidCredentials => 'The email or password is incorrect.',
        };
    }
}
