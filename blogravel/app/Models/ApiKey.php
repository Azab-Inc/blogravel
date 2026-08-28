<?php

namespace App\Models;

use App\Enums\ApiKeyAbility;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at'])]
#[Hidden(['token'])]
class ApiKey extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'abilities' => AsEnumCollection::class.':'.ApiKeyAbility::class,
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
