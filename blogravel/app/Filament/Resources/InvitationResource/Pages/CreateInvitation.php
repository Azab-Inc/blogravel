<?php

namespace App\Filament\Resources\InvitationResource\Pages;

use App\Filament\Resources\InvitationResource;
use App\Jobs\SendInvitationJob;
use App\Models\Invitation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

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
        }

        Notification::make()
            ->title('Invitation created')
            ->body($invitation->type === 'email'
                ? "Invitation email sent to {$invitation->email}"
                : 'Shareable link created')
            ->success()
            ->send();
    }
}
