<?php

use App\Enums\DeletionReason;
use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('records self closure and closes the tenant for the last admin', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $author = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);

    app(AccountLifecycleService::class)->close($admin, 'Acme');

    $deleted = User::withTrashed()->findOrFail($admin->id);

    expect($deleted->deletion_reason)->toBe(DeletionReason::SelfClosed)
        ->and($deleted->deleted_by)->toBeNull()
        ->and(User::find($author->id))->not->toBeNull();
    $this->assertSoftDeleted('users', ['id' => $admin->id]);
    $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
});

it('preserves a tenant when a non-last administrator closes their account', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $otherAdmin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);

    app(AccountLifecycleService::class)->close($admin);

    expect(User::withTrashed()->findOrFail($admin->id)->deletion_reason)
        ->toBe(DeletionReason::SelfClosed)
        ->and(User::find($otherAdmin->id))->not->toBeNull()
        ->and(Tenant::withTrashed()->findOrFail($tenant->id)->deleted_at)->toBeNull();
});

it('requires matching tenant confirmation before closing the last administrator', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $admin = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);

    expect(fn () => app(AccountLifecycleService::class)->close($admin, 'Wrong tenant'))
        ->toThrow(ValidationException::class);

    expect(User::find($admin->id))->not->toBeNull()
        ->and(Tenant::find($tenant->id))->not->toBeNull();
});

it('records an administrator removal and makes it non-self-recoverable', function () {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $target = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);

    app(AccountLifecycleService::class)->remove($actor, $target);

    $deleted = User::withTrashed()->findOrFail($target->id);

    expect($deleted->deletion_reason)->toBe(DeletionReason::AdminRemoved)
        ->and($deleted->deleted_by)->toBe($actor->id);
    $this->assertSoftDeleted('users', ['id' => $target->id]);
});

it('removes only authorized records through the bulk administrator action', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $actor = User::factory()->forTenant($tenant)->create(['role' => Role::Admin]);
    $target = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);
    $otherTarget = User::factory()->forTenant($otherTenant)->create(['role' => Role::Author]);

    $this->actingAs($actor);

    Livewire::test(ListUsers::class)
        ->assertTableBulkActionExists(DeleteBulkAction::class)
        ->callTableBulkAction(DeleteBulkAction::class, [$target, $otherTarget]);

    expect(User::find($target->id))->toBeNull()
        ->and(User::withoutGlobalScopes()->find($otherTarget->id))->not->toBeNull();
    expect(User::withTrashed()->findOrFail($target->id)->deletion_reason)
        ->toBe(DeletionReason::AdminRemoved);
});

it('excludes soft-deleted tenants from tenant-scoped queries', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create(['role' => Role::Author]);
    Post::factory()->create(['tenant_id' => $tenant->id, 'author_id' => $user->id]);
    $tenant->delete();

    $this->actingAs($user);

    expect(Post::where('tenant_id', $tenant->id)->get())->toBeEmpty();
});
