<?php

namespace App\Filament\Pages;

use App\Enums\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSetup extends SimplePage
{
    protected static ?string $title = 'Tenant Setup';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->authorizedUser();
        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tenant name')
                    ->required()
                    ->string()
                    ->maxLength(255)
                    ->autofocus(),
            ]);
    }

    public function createTenant(): void
    {
        $user = $this->authorizedUser();
        $data = $this->form->getState();

        DB::transaction(function () use ($data, $user): void {
            $tenant = Tenant::create([
                'domain' => 'recovered-'.Str::uuid(),
                'name' => $data['name'],
                'plan' => Plan::Free,
            ]);

            $user->update(['tenant_id' => $tenant->getKey()]);
        });

        session()->forget('recovery.needs_tenant_setup');
        $this->redirect(filament()->getUrl());
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('createTenant')
                ->label('Create tenant')
                ->submit('createTenant'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('createTenant')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->fullWidth()
                            ->key('form-actions'),
                    ]),
            ]);
    }

    private function authorizedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User
                && $user->isAdmin()
                && $user->tenant_id === null
                && session('recovery.needs_tenant_setup') === $user->getKey(),
            403,
        );

        return $user;
    }
}
