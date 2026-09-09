<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Filament\Resources\InvitationResource;
use App\Jobs\SendInvitationJob;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Js;

class CreateInvitation extends CreateRecord
{
    protected static string $resource = InvitationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['token'] = Invitation::generateToken();
        $data['invited_by'] = auth()->id();

        if (! isset($data['type'])) {
            $data['type'] = 'email';
        }

        if ($data['type'] === 'shareable') {
            $data['email'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $invitation = $this->record;

        if ($invitation->type === 'email') {
            SendInvitationJob::dispatch($invitation);

            Notification::make()
                ->title('Invitation created')
                ->body("Invitation email sent to {$invitation->email}")
                ->success()
                ->send();

            return;
        }

        $link = route('invitations.accept', ['token' => $invitation->token]);

        Notification::make()
            ->title('Shareable link created')
            ->body('The link below can be shared with anyone. It expires '.$invitation->expires_at?->format('M j, Y').'.')
            ->success()
            ->actions([
                Action::make('copyLink')
                    ->label('Copy link')
                    ->icon('heroicon-m-clipboard')
                    ->button()
                    ->alpineClickHandler('
                        window.navigator.clipboard.writeText('.Js::from($link).');
                        $tooltip('.Js::from('Copied to clipboard!').', { theme: $store.theme });
                    '),
                Action::make('openLink')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url($link)
                    ->color('gray'),
            ])
            ->persistent()
            ->send();
    }
}
