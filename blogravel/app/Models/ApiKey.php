<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ApiKeyAbility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'name', 'token', 'key_hash', 'abilities', 'rate_limit_per_minute', 'last_used_at', 'expires_at'])]
#[Hidden(['token'])]
class ApiKey extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'abilities' => AsEnumCollection::class.':'.ApiKeyAbility::class,
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'rate_limit_per_minute' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function booted(): void
    {
        static::creating(function (ApiKey $key) {
            if ($key->token && ! $key->key_hash) {
                $key->key_hash = hash('sha256', $key->token);
            }
        });
    }

    public function setPlaintext(string $plaintext): void
    {
        $this->key_hash = hash('sha256', $plaintext);
        $this->save();
    }

    public function resolveRateLimit(): int
    {
        return $this->rate_limit_per_minute ?? 100;
    }
}
