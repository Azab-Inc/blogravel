<?php

use App\Filament\Widgets\OpenModeWarning;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;

test("widget is visible when no API keys exist", function () {
    ApiKey::query()->delete();
    $widget = new OpenModeWarning();
    expect($widget->getApiKeyCount())->toBe(0)->and($widget->isVisible())->toBeTrue();
});

test("widget is hidden when API keys exist", function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(["tenant_id" => $tenant->id]);
    ApiKey::factory()->create(["tenant_id" => $tenant->id]);
    $widget = new OpenModeWarning();
    expect($widget->getApiKeyCount())->toBe(1)->and($widget->isVisible())->toBeFalse();
});

test("widget reflects current DB state on each call", function () {
    ApiKey::query()->delete();
    $widget = new OpenModeWarning();
    expect($widget->getApiKeyCount())->toBe(0);
    $tenant = Tenant::factory()->create();
    ApiKey::factory()->create(["tenant_id" => $tenant->id]);
    expect($widget->getApiKeyCount())->toBe(1)->and($widget->isVisible())->toBeFalse();
});
