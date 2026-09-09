<?php

use App\Filament\Widgets\OpenModeWarning;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;

test('widget is visible when no API keys exist for tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    expect(OpenModeWarning::canView())->toBeTrue();
});

test('widget is hidden when API keys exist for tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    ApiKey::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    expect(OpenModeWarning::canView())->toBeFalse();
});

test('widget is visible when other tenants have keys but current tenant has none', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    ApiKey::factory()->create(['tenant_id' => $tenantB->id]);
    $this->actingAs($userA);

    expect(OpenModeWarning::canView())->toBeTrue();
});

test('widget is hidden for users without a tenant', function () {
    $user = User::factory()->create(['tenant_id' => null]);
    $this->actingAs($user);

    expect(OpenModeWarning::canView())->toBeTrue();
});

test('widget reflects current DB state on each call', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->actingAs($user);

    expect(OpenModeWarning::canView())->toBeTrue();

    ApiKey::factory()->create(['tenant_id' => $tenant->id]);
    expect(OpenModeWarning::canView())->toBeFalse();
});
