<?php

namespace App\Filament\Pages\Auth;

use App\Services\AccountLifecycleService;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Profile Settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile Information')
                    ->schema([
                        $this->getFirstNameFormComponent(),
                        $this->getLastNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                Section::make('Change Password')
                    ->description('Leave blank to keep current password.')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
                Section::make('Danger Zone')
                    ->description('Closing your account will soft-delete your profile. You have 30 days to recover it by contacting support.')
                    ->schema([
                        Placeholder::make('closure_warning')
                            ->content(function () {
                                $user = $this->getUser();
                                if (app(AccountLifecycleService::class)->isLastAdministrator($user)) {
                                    return 'You are the only administrator. Closing this account will also close your tenant. You have 30 days to recover your account and tenant by contacting support.';
                                }

                                return null;
                            }),
                        $this->getCloseAccountFormAction(),
                    ]),
            ]);
    }

    protected function getFirstNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label('First Name')
            ->required()
            ->maxLength(255);
    }

    protected function getLastNameFormComponent(): Component
    {
        return TextInput::make('last_name')
            ->label('Last Name')
            ->required()
            ->maxLength(255);
    }

    protected function getCloseAccountFormAction(): Action
    {
        return Action::make('closeAccount')
            ->label('Close Account')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->requiresConfirmation()
            ->modalHeading('Close Account')
            ->modalDescription('Are you sure you want to close your account? If you are the last administrator, your tenant will also be closed. This action can be reversed within 30 days by contacting support.')
            ->modalSubmitActionLabel('Yes, Close My Account')
            ->form(fn (): array => $this->getCloseAccountForm())
            ->action(function (array $data, AccountLifecycleService $lifecycle): void {
                $this->closeAccount($data['tenant_confirmation'] ?? null, $lifecycle);
            });
    }

    public function closeAccount(?string $tenantConfirmation = null, ?AccountLifecycleService $lifecycle = null): void
    {
        $user = $this->getUser();
        $lifecycle ??= app(AccountLifecycleService::class);

        $lifecycle->close($user, $tenantConfirmation);

        Auth::logout();

        Notification::make()
            ->title('Account closed')
            ->body('Your account has been closed. You have 30 days to recover it by contacting support.')
            ->success()
            ->send();

        $this->redirect(route('filament.admin.auth.login'));
    }

    protected function getCloseAccountForm(): array
    {
        $user = $this->getUser();
        $tenant = $user->tenant;

        if ($tenant === null || ! app(AccountLifecycleService::class)->isLastAdministrator($user)) {
            return [];
        }

        return [
            TextInput::make('tenant_confirmation')
                ->label('Type the tenant name or slug to confirm')
                ->required()
                ->in(array_values(array_filter([$tenant->name, $tenant->slug]))),
        ];
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->visible(true)
            ->required(fn (Get $get): bool => filled($get('password')));
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => filled($get('password')) || ($get('email') !== $this->getUser()->getAttributeValue('email')));
    }
}
