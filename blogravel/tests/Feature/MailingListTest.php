<?php

use App\Enums\PostStatus;
use App\Enums\SubscriberStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PostPublished;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'mailingtest.com']);
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'laravel']);
    $this->author = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('creates a pending subscriber and sends confirmation email', function () {
    Notification::fake();

    $response = $this->postJson(route('api.subscribe').'?tenant='.$this->tenant->domain, [
        'email' => 'newsubscriber@example.com',
        'categories' => [$this->category->id],
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('subscribers', [
        'tenant_id' => $this->tenant->id,
        'email' => 'newsubscriber@example.com',
        'status' => SubscriberStatus::Pending->value,
    ]);

    $subscriber = Subscriber::where('email', 'newsubscriber@example.com')->first();
    $this->assertNotNull($subscriber->confirmation_token);
    $this->assertTrue($subscriber->categories->contains($this->category->id));
});

it('returns 201 with empty categories for subscribe-all', function () {
    $response = $this->postJson(route('api.subscribe').'?tenant='.$this->tenant->domain, [
        'email' => 'all@example.com',
    ]);

    $response->assertCreated();
    $subscriber = Subscriber::where('email', 'all@example.com')->first();
    $this->assertTrue($subscriber->categories->isEmpty());
});

it('returns 422 for invalid email', function () {
    $response = $this->postJson(route('api.subscribe').'?tenant='.$this->tenant->domain, [
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable();
});

it('returns 200 when confirming subscriber', function () {
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Pending,
        'confirmation_token' => Str::random(64),
    ]);

    $response = $this->get(route('api.confirm', ['token' => $subscriber->confirmation_token]));

    $response->assertOk();
    $subscriber->refresh();
    $this->assertTrue($subscriber->status === SubscriberStatus::Subscribed);
});

it('returns 404 for invalid confirmation token', function () {
    $response = $this->get(route('api.confirm', ['token' => 'invalid-token']));
    $response->assertNotFound();
});

it('unsubscribes a subscriber', function () {
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Subscribed,
        'confirmation_token' => Str::random(64),
    ]);

    $response = $this->get(route('api.unsubscribe', ['token' => $subscriber->confirmation_token]));

    $response->assertOk();
    $subscriber->refresh();
    $this->assertTrue($subscriber->status === SubscriberStatus::Unsubscribed);
});

it('returns 404 for invalid unsubscribe token', function () {
    $response = $this->get(route('api.unsubscribe', ['token' => 'invalid-token']));
    $response->assertNotFound();
});

it('sends notification to matching category subscribers on publish', function () {
    Notification::fake();

    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Subscribed,
    ]);
    $subscriber->categories()->attach($this->category);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    $post->categories()->attach($this->category);

    $this->artisan('mailing:notify-subscribers', ['post_id' => $post->id]);

    Notification::assertSentTo($subscriber, PostPublished::class);
});

it('does not notify subscriber for non-matching category', function () {
    Notification::fake();

    $otherCategory = Category::factory()->create(['tenant_id' => $this->tenant->id, 'slug' => 'vue']);
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Subscribed,
    ]);
    $subscriber->categories()->attach($otherCategory);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    $post->categories()->attach($this->category);

    $this->artisan('mailing:notify-subscribers', ['post_id' => $post->id]);

    Notification::assertNotSentTo($subscriber, PostPublished::class);
});

it('notifies subscriber with empty categories (subscribed to all)', function () {
    Notification::fake();

    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Subscribed,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    $post->categories()->attach($this->category);

    $this->artisan('mailing:notify-subscribers', ['post_id' => $post->id]);

    Notification::assertSentTo($subscriber, PostPublished::class);
});

it('does not notify unsubscribed subscribers', function () {
    Notification::fake();

    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => SubscriberStatus::Unsubscribed,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $this->artisan('mailing:notify-subscribers', ['post_id' => $post->id]);

    Notification::assertNothingSent();
});

it('does not notify subscribers from other tenants', function () {
    Notification::fake();

    $otherTenant = Tenant::factory()->create(['domain' => 'other.com']);
    $subscriber = Subscriber::factory()->create([
        'tenant_id' => $otherTenant->id,
        'status' => SubscriberStatus::Subscribed,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->author->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    $post->categories()->attach($this->category);

    $this->artisan('mailing:notify-subscribers', ['post_id' => $post->id]);

    Notification::assertNothingSent();
});
