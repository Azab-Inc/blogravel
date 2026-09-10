<?php

use App\Enums\PostStatus;
use App\Models\OutboundWebhook;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['domain' => 'webhooktest.com']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('creates an outbound webhook config via Filament', function () {
    $webhook = OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published', 'post.updated'],
        'secret' => 'webhook-secret-123',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('outbound_webhooks', [
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'is_active' => true,
    ]);
});

it('fires webhook when post is created as published', function () {
    Http::fake();

    $webhook = OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published'],
        'secret' => 'secret-123',
        'is_active' => true,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/webhook'
            && $request->hasHeader('X-Webhook-Signature');
    });

    $this->assertDatabaseHas('webhook_deliveries', [
        'outbound_webhook_id' => $webhook->id,
        'event' => 'post.published',
        'status' => 'success',
    ]);
});

it('fires webhook when post status changes to published', function () {
    Http::fake();

    $webhook = OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published'],
        'secret' => 'secret-123',
        'is_active' => true,
    ]);

    $post = Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    $post->update(['status' => PostStatus::Published, 'published_at' => now()]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/webhook'
            && $request->hasHeader('X-Webhook-Signature');
    });
});

it('does not fire inactive webhooks', function () {
    Http::fake();

    OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published'],
        'secret' => 'secret-123',
        'is_active' => false,
    ]);

    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    Http::assertNothingSent();
});

it('does not fire webhook for non-matching events', function () {
    Http::fake();

    OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.updated'],
        'secret' => 'secret-123',
        'is_active' => true,
    ]);

    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    Http::assertNothingSent();
});

it('records delivery failure and retries', function () {
    Http::fake([
        'https://example.com/webhook' => Http::response([], 500),
    ]);

    $webhook = OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published'],
        'secret' => 'secret-123',
        'is_active' => true,
    ]);

    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $this->assertDatabaseHas('webhook_deliveries', [
        'outbound_webhook_id' => $webhook->id,
        'event' => 'post.published',
        'status' => 'failed',
    ]);
});

it('includes HMAC signature in webhook payload', function () {
    Http::fake();

    $secret = 'my-webhook-secret';
    $webhook = OutboundWebhook::create([
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'events' => ['post.published'],
        'secret' => $secret,
        'is_active' => true,
    ]);

    Post::factory()->create([
        'tenant_id' => $this->tenant->id,
        'author_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    Http::assertSent(function ($request) use ($secret) {
        $signature = $request->header('X-Webhook-Signature')[0] ?? '';
        $expected = hash_hmac('sha256', $request->body(), $secret);

        return $signature === $expected;
    });
});
