<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

#[Fillable(['domain', 'slug', 'custom_domain', 'name', 'plan'])]
class Tenant extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if ($tenant->slug) {
                return;
            }

            $baseSlug = Str::slug($tenant->name) ?: 'tenant-'.$tenant->getKey();
            $slug = $baseSlug;
            $suffix = 2;

            while (static::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix;
                $suffix++;
            }

            $tenant->slug = $slug;
            $tenant->slugWasGenerated = true;
        });
    }

    private bool $slugWasGenerated = false;

    public function save(array $options = []): bool
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return parent::save($options);
            } catch (QueryException $exception) {
                if (! $this->slugWasGenerated || $this->exists || ! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $this->slug = $this->nextAvailableSlug($this->slug);
            }
        }

        throw $exception;
    }

    public function setCustomDomainAttribute(?string $value): void
    {
        $this->attributes['custom_domain'] = $value === null ? null : strtolower(trim($value));
    }

    private function nextAvailableSlug(string $slug): string
    {
        $baseSlug = preg_replace('/-\d+$/', '', $slug) ?: $slug;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $baseSlug.'-'.$suffix)->exists()) {
            $suffix++;
        }

        return $baseSlug.'-'.$suffix;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains($message, 'slug');
    }

    protected $casts = [
        'plan' => Plan::class,
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function aiProviders(): HasMany
    {
        return $this->hasMany(AiProvider::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function backupRules(): HasMany
    {
        return $this->hasMany(BackupRule::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }
}
