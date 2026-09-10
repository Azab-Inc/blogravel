<?php

namespace App\Models;

use App\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['tenant_id', 'email', 'name', 'status', 'confirmation_token'])]
class Subscriber extends BaseModel
{
    use HasFactory, Notifiable;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'subscriber_category');
    }

    public static function booted(): void
    {
        static::creating(function (Subscriber $subscriber) {
            if (is_null($subscriber->confirmation_token)) {
                $subscriber->confirmation_token = Str::random(64);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
        ];
    }
}
