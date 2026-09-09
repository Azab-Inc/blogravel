<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['tenant_id', 'email', 'role', 'token', 'accepted_at', 'expires_at', 'invited_by', 'type'])]
#[Hidden(['token'])]
class Invitation extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
            'role' => Role::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
