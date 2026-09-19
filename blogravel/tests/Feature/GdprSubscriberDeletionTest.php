<?php

use App\Enums\Role;
use App\Enums\SubscriberStatus;
use App\Filament\Resources\SubscriberResource\Pages\ListSubscribers;
use App\Models\Category;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'gdprtest.com']);
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'laravel']);
});

it('deletes a subscriber via token', function () {
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Subscribed,
        'unsubscribe_token' => Str::random(64),
    ]);
    $subscriber->categories()->attach($this->category);

    $response = $this->call('DELETE', '/api/v1/subscribers/'.$subscriber->unsubscribe_token);

    $response->assertOk();
    $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
});

it('returns 404 for invalid deletion token', function () {
    $response = $this->call('DELETE', '/api/v1/subscribers/invalid-token');
    $response->assertNotFound();
});

it('cannot resurrect a deleted subscriber by re-subscribing with same email', function () {
    $email = 'deleteme@example.com';

    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => $email,
        'status' => SubscriberStatus::Subscribed,
        'unsubscribe_token' => Str::random(64),
    ]);

    $this->call('DELETE', '/api/v1/subscribers/'.$subscriber->unsubscribe_token);

    // Re-subscribe with same email creates a new record
    $this->postJson(route('api.subscribe').'?tenant='.$this->tenant->domain, [
        'email' => $email,
    ])->assertCreated();

    $this->assertDatabaseCount('subscribers', 1);
    $newSubscriber = Subscriber::where('email', $email)->first();
    $this->assertTrue($newSubscriber->id !== $subscriber->id);
    $this->assertTrue($newSubscriber->status === SubscriberStatus::Pending);
});

it('cascades deletion to category pivots', function () {
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'unsubscribe_token' => Str::random(64),
    ]);
    $subscriber->categories()->attach($this->category);

    $this->assertDatabaseHas('subscriber_category', ['subscriber_id' => $subscriber->id]);

    $this->call('DELETE', '/api/v1/subscribers/'.$subscriber->unsubscribe_token);

    $this->assertDatabaseMissing('subscriber_category', ['subscriber_id' => $subscriber->id]);
});

it('preserves unsubscribe_token after confirmation', function () {
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Pending,
        'confirmation_token' => Str::random(64),
        'unsubscribe_token' => Str::random(64),
    ]);

    $this->get(route('api.confirm', ['token' => $subscriber->confirmation_token]));

    $subscriber->refresh();
    $this->assertNull($subscriber->confirmation_token);
    $this->assertNotNull($subscriber->unsubscribe_token);
});

it('allows an authenticated admin to permanently delete a subscriber from Filament', function () {
    $admin = User::factory()->forTenant($this->tenant)->create([
        'role' => Role::SuperAdmin,
    ]);
    $subscriber = Subscriber::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($admin);

    Livewire::test(ListSubscribers::class)
        ->callTableAction('delete', $subscriber)
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
});
