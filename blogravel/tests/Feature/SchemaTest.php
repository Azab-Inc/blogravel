<?php

use App\Enums\ApiKeyAbility;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a tenant and its owned users and posts', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $post = Post::factory()->for($tenant)->for($user, 'author')->create();

    expect($tenant->users)->toHaveCount(1);
    expect($tenant->posts)->toHaveCount(1);
    expect($post->tenant->is($tenant))->toBeTrue();
    expect($post->author->is($user))->toBeTrue();
});

it('associates posts with categories and tags through pivot tables', function () {
    $tenant = Tenant::factory()->create();
    $post = Post::factory()->for($tenant)->create();
    $category = Category::factory()->for($tenant)->create();
    $tag = Tag::factory()->for($tenant)->create();

    $post->categories()->attach($category);
    $post->tags()->attach($tag);

    expect($post->categories)->toHaveCount(1);
    expect($post->tags)->toHaveCount(1);
    expect($category->posts)->toHaveCount(1);
    expect($tag->posts)->toHaveCount(1);
});

it('links comments and media to their tenant and post', function () {
    $tenant = Tenant::factory()->create();
    $post = Post::factory()->for($tenant)->create();
    $comment = Comment::factory()->for($tenant)->for($post)->create();
    $media = Media::factory()->for($tenant)->create();

    expect($post->comments)->toHaveCount(1);
    expect($comment->post->is($post))->toBeTrue();
    expect($tenant->media)->toHaveCount(1);
});

it('manages pages and subscribers with category preferences', function () {
    $tenant = Tenant::factory()->create();
    $page = Page::factory()->for($tenant)->create();
    $subscriber = Subscriber::factory()->for($tenant)->create();
    $category = Category::factory()->for($tenant)->create();

    $subscriber->categories()->attach($category);

    expect($tenant->pages)->toHaveCount(1);
    expect($page->tenant->is($tenant))->toBeTrue();
    expect($subscriber->categories)->toHaveCount(1);
});

it('stores per-tenant integrations: ai providers, api keys, settings, webhooks, invitations, subscriptions', function () {
    $tenant = Tenant::factory()->create();
    $aiProvider = AiProvider::factory()->for($tenant)->create();
    $apiKey = ApiKey::factory()->for($tenant)->create(['abilities' => [ApiKeyAbility::Read]]);
    $setting = Setting::factory()->for($tenant)->create();
    $webhook = Webhook::factory()->for($tenant)->create();
    $invitation = Invitation::factory()->for($tenant)->create();
    $subscription = Subscription::factory()->for($tenant)->create();

    expect($tenant->aiProviders)->toHaveCount(1);
    expect($apiKey->abilities)->toBe([ApiKeyAbility::Read]);
    expect($tenant->settings)->toHaveCount(1);
    expect($tenant->webhooks)->toHaveCount(1);
    expect($tenant->invitations)->toHaveCount(1);
    expect($tenant->subscriptions)->toHaveCount(1);
    expect($aiProvider->tenant->is($tenant))->toBeTrue();
});

it('scopes every record to its owning tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Post::factory()->for($tenantA)->create();
    Post::factory()->for($tenantB)->create();

    expect($tenantA->posts)->toHaveCount(1);
    expect($tenantB->posts)->toHaveCount(1);
    expect(Tenant::count())->toBe(2);
});
